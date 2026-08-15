<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CycleOverageSettlement;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use App\Services\BudgetService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function __construct(
        private TelegramService $telegram,
        private BudgetService $budgetService,
        private BillingCycleService $cycleService,
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        $secret = Setting::getValue('telegram_webhook_secret', '');
        $receivedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (mb_strlen($secret) < 32 || !hash_equals($secret, $receivedSecret)) {
            Log::warning('Rejected Telegram webhook request', ['ip' => $request->ip()]);
            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }

        if (isset($update['message']['text'])) {
            $text = trim($update['message']['text']);
            if ($text === '/report' || str_starts_with($text, '/report@')) {
                $this->handleReport();
            }
            if ($text === '/advice' || str_starts_with($text, '/advice@')) {
                $this->handleAdvice();
            }
        }

        return response()->json(['ok' => true]);
    }

    private function handleReport(): void
    {
        $cycle = $this->cycleService->getCurrentCycle();
        $week = $this->cycleService->getCurrentWeek();

        $text = "📊 تقرير المصاريف\n";
        $text .= "━━━━━━━━━━━━━\n";
        $cycleLabel = $cycle->is_open
            ? 'بدأت ' . $cycle->start_date->format('d/m') . ' — حتى الراتب القادم'
            : $cycle->start_date->format('d/m') . ' - ' . $cycle->end_date->format('d/m');
        $text .= "الدورة: {$cycleLabel}\n";
        $text .= "الأسبوع: {$week->week_number}\n\n";

        $budgets = Budget::with('category')->where('monthly_amount', '>', 0)->get();
        $totalSpent = 0;
        $totalBudget = 0;

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            $cycleSpent = $this->budgetService->getCycleSpent($cat->id, $cycle->id);
            $monthlyPct = round(($cycleSpent / $budget->monthly_amount) * 100);

            $text .= "{$cat->icon} {$cat->name}\n";
            $text .= "  الشهري: " . number_format($cycleSpent, 0) . " / " . number_format($budget->monthly_amount, 0) . " ريال ({$monthlyPct}%)\n";

            if ($cat->show_in_weekly) {
                $weeklySpent = $this->budgetService->getWeeklySpent($cat->id, $week->id);
                $weeklyAllowance = $this->budgetService->getWeeklyAllowance($cat->id, $cycle, $week);
                if ($weeklyAllowance !== null && $weeklyAllowance > 0) {
                    $weeklyPct = round(($weeklySpent / $weeklyAllowance) * 100);
                    $text .= "  الأسبوعي: " . number_format($weeklySpent, 0) . " / " . number_format($weeklyAllowance, 0) . " ريال ({$weeklyPct}%)\n";
                }
            }

            $text .= "\n";
            $totalSpent += $cycleSpent;
            $totalBudget += $budget->monthly_amount;
            $remaining = $budget->monthly_amount - $cycleSpent;
        }

        $text .= "━━━━━━━━━━━━━\n";
        $text .= "إجمالي المصاريف: " . number_format($totalSpent, 0) . " ريال\n";
        $text .= "إجمالي الميزانيات: " . number_format($totalBudget, 0) . " ريال\n";
        $text .= "المتبقي: " . number_format($totalBudget - $totalSpent, 0) . " ريال";

        $this->telegram->sendMessage($text);
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $callbackQueryId = $callbackQuery['id'] ?? '';

        // Format: classify:{transaction_id}:{category_id}
        if (!str_starts_with($data, 'classify:')) {
            return;
        }

        $parts = explode(':', $data);
        if (count($parts) !== 3) {
            return;
        }

        $transactionId = (int) $parts[1];
        $categoryId = (int) $parts[2];

        $transaction = Transaction::find($transactionId);
        $category = Category::find($categoryId);

        if (!$transaction || !$category) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'خطأ: العملية غير موجودة');
            return;
        }

        if ($transaction->is_classified) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'العملية مصنفة مسبقاً');
            return;
        }

        // Classify
        $transaction->update([
            'category_id' => $categoryId,
            'is_classified' => true,
            'classified_at' => now(),
            'needs_reminder' => false,
        ]);

        // Update telegram message
        $this->telegram->updateClassifiedMessage($transaction->fresh(['category']));

        // Answer callback
        $this->telegram->answerCallbackQuery($callbackQueryId, "✅ {$category->icon} {$category->name}");

        // Check budget alerts
        $this->budgetService->checkAndAlert($transaction->fresh(['category', 'cycle', 'week']));
    }

    private function handleAdvice(): void
    {
        $cycle = $this->cycleService->getCurrentCycle();
        $week = $this->cycleService->getCurrentWeek();
        $budgets = Budget::with('category')->get();

        // Build data summary for AI
        $data = "بيانات المصاريف:\n";
        $cycleLabel = $cycle->is_open
            ? 'بدأت ' . $cycle->start_date->format('d/m') . ' — حتى الراتب القادم'
            : $cycle->start_date->format('d/m') . ' - ' . $cycle->end_date->format('d/m');
        $data .= "الدورة: {$cycleLabel}\n";
        $totalWeeks = $cycle->weeks()->count();
        $data .= "الأسبوع الحالي: {$week->week_number} من {$totalWeeks}\n";
        $data .= "أيام متبقية في الأسبوع: " . max(0, now()->diffInDays($week->end_date, false)) . "\n";
        if (!$cycle->is_open) {
            $data .= "أيام متبقية في الدورة: " . max(0, now()->diffInDays($cycle->end_date, false)) . "\n";
        }
        $data .= "\n";

        $totalSpent = 0;
        $totalBudget = 0;
        $baseBudgetTotal = 0;
        $totalRemaining = 0;
        $savingsRemaining = 0;
        $savingsCategoryId = (int) Setting::getValue('savings_category_id', '0');
        $categoryData = [];

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            if (!$cat) {
                continue;
            }
            $cycleSpent = $this->budgetService->getCycleSpent($cat->id, $cycle->id);
            $baseBudget = (float) $budget->monthly_amount;
            $carried = (float) $cat->carried_balance;
            $effectiveBudget = $baseBudget + $carried;
            $monthlyPct = $effectiveBudget > 0 ? round(($cycleSpent / $effectiveBudget) * 100) : 0;
            $remaining = $effectiveBudget - $cycleSpent;

            $data .= "{$cat->name}: صرف " . number_format($cycleSpent, 0) . " من " . number_format($effectiveBudget, 0) . " ريال ({$monthlyPct}%)";
            if ($carried > 0) {
                $data .= ' — يشمل ' . number_format($carried, 0) . " ريال مُرحّلة";
            }
            $data .= "\n";

            $weeklyData = null;
            if ($cat->show_in_weekly) {
                $weeklySpent = $this->budgetService->getWeeklySpent($cat->id, $week->id);
                $weeklyAllowance = $this->budgetService->getWeeklyAllowance($cat->id, $cycle, $week);
                if ($weeklyAllowance !== null && $weeklyAllowance > 0) {
                    $data .= "  الأسبوعي: " . number_format($weeklySpent, 0) . " / " . number_format($weeklyAllowance, 0) . " ريال\n";
                    $weeklyData = [
                        'spent' => round($weeklySpent, 2),
                        'allowance' => round($weeklyAllowance, 2),
                        'remaining' => round($weeklyAllowance - $weeklySpent, 2),
                        'percent' => round(($weeklySpent / $weeklyAllowance) * 100),
                    ];
                }
            }

            if ($effectiveBudget > 0 || $cycleSpent > 0) {
                $categoryData[] = [
                    'name' => $cat->name,
                    'base_budget' => round($baseBudget, 2),
                    'carried_balance' => round($carried, 2),
                    'effective_budget' => round($effectiveBudget, 2),
                    'spent' => round($cycleSpent, 2),
                    'remaining' => round($remaining, 2),
                    'percent_used' => $monthlyPct,
                    'is_savings' => $cat->id === $savingsCategoryId,
                    'weekly' => $weeklyData,
                ];
            }

            $totalSpent += $cycleSpent;
            $totalBudget += $effectiveBudget;
            $baseBudgetTotal += $baseBudget;
            $totalRemaining += max(0, $remaining);
            if ($cat->id === $savingsCategoryId) {
                $savingsRemaining = max(0, $remaining);
            }
        }

        $incomeTotal = (float) Transaction::where('cycle_id', $cycle->id)->where('type', 'income')->sum('amount');
        $unallocatedIncome = $incomeTotal - $baseBudgetTotal;
        $data .= "\nإجمالي المصاريف المصنفة: " . number_format($totalSpent, 0) . " ريال";
        $data .= "\nالمتبقي داخل البنود: " . number_format($totalRemaining, 0) . " ريال (مبلغ موزع على البنود وليس رصيداً حراً)";
        $data .= "\nالمتبقي المحمي في بند الادخار: " . number_format($savingsRemaining, 0) . " ريال";
        $data .= "\nغير المخصص من الدخل المسجل: " . number_format($unallocatedIncome, 0) . " ريال";
        $data .= "\nقاعدة التقرير: لا تعامل المتبقي داخل البنود كرَصيد حر؛ هو مخصص للبنود المذكورة.";
        $data .= "\nقاعدة التقرير: لا تصف الأسبوع بأنه الأخير إلا إذا كانت البيانات تؤكد ذلك، ولا تقترح نقل فائض بند إلى بند آخر؛ التسوية المالية تُدار من إعدادات النظام عند إغلاق الدورة.";
        $data .= "\nقاعدة التقرير: وصول بند الادخار إلى 100% يعني تنفيذ خطة الادخار بنجاح وليس تنبيهاً سلبياً. والبنود الثابتة مثل القروض والالتزامات لا تُذكر كبند عاجل عند 100% إلا إذا تجاوزت ميزانيتها.";

        $unclassifiedExpenses = Transaction::where('cycle_id', $cycle->id)
            ->where('is_classified', false)
            ->whereIn('type', ['purchase', 'transfer', 'atm']);
        $unbudgetedExpenses = Transaction::where('cycle_id', $cycle->id)
            ->where('is_classified', true)
            ->whereNull('category_id')
            ->whereIn('type', ['purchase', 'transfer', 'atm']);
        $overageSourceId = (int) Setting::getValue('overage_source_category_id', '0');
        $overageSource = $overageSourceId ? Category::find($overageSourceId) : null;
        $lastSettlement = CycleOverageSettlement::with('sourceCategory')
            ->latest('created_at')
            ->first();

        $reportData = [
            'report_date' => now()->format('Y-m-d'),
            'cycle' => [
                'start_date' => $cycle->start_date->format('Y-m-d'),
                'end_date' => $cycle->end_date?->format('Y-m-d'),
                'awaiting_salary' => $cycle->is_open,
                'current_week' => $week->week_number,
                'total_weeks' => $totalWeeks,
                'is_final_week' => $week->week_number >= $totalWeeks,
                'days_left_in_week' => max(0, now()->diffInDays($week->end_date, false)),
            ],
            'income' => [
                'recorded' => round($incomeTotal, 2),
                'unallocated_after_base_budgets' => round($unallocatedIncome, 2),
            ],
            'totals' => [
                'spent_in_categories' => round($totalSpent, 2),
                'effective_category_budgets' => round($totalBudget, 2),
                'remaining_inside_categories' => round($totalRemaining, 2),
                'protected_savings_remaining' => round($savingsRemaining, 2),
            ],
            'data_quality' => [
                'unclassified_expenses_count' => $unclassifiedExpenses->count(),
                'unclassified_expenses_amount' => round((float) $unclassifiedExpenses->sum('amount'), 2),
                'unbudgeted_expenses_count' => $unbudgetedExpenses->count(),
                'unbudgeted_expenses_amount' => round((float) $unbudgetedExpenses->sum('amount'), 2),
            ],
            'cycle_closure_rules' => [
                'auto_settle_overages' => Setting::getValue('auto_settle_overages', '0') === '1',
                'settlement_source_category' => $overageSource?->name,
                'last_settlement' => $lastSettlement ? [
                    'covered_amount' => round((float) $lastSettlement->covered_amount, 2),
                    'uncovered_amount' => round((float) $lastSettlement->uncovered_amount, 2),
                    'source_category' => $lastSettlement->sourceCategory?->name,
                ] : null,
            ],
            'categories' => $categoryData,
        ];
        $data = json_encode($reportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);

        $apiKey = Setting::getValue('gemini_api_key', '');
        if (empty($apiKey)) {
            $this->telegram->sendMessage('⚠️ مفتاح Gemini غير مضبوط في الإعدادات');
            return;
        }

        $prompt = <<<PROMPT
أنت مستشار مالي شخصي. حلل بيانات المصاريف التالية وقدم تقريراً مختصراً بالعربي يتضمن:

1. تقييم عام للوضع المالي هذه الدورة
2. البنود التي تحتاج انتباه (متجاوزة أو قريبة من الحد)
3. 3-4 نصائح عملية محددة للأيام المتبقية
4. اقتراح لتحسين توزيع الميزانية إن وجد

اجعل الرد مختصراً ومباشراً ومناسباً لرسالة تليجرام (بدون markdown، استخدم إيموجي للتنظيم).

{$data}
PROMPT;

        $prompt = <<<PROMPT
أنت محلل مالي شخصي دقيق لتطبيق متابعة المصاريف. ستستلم أدناه بيانات مالية منظمة بصيغة JSON للدورة الحالية.

مهمتك: إعداد تقرير عربي عملي ومختصر يصلح مباشرة لرسالة تيليجرام.

قواعد مالية ملزمة:
- استخدم الأرقام الواردة فقط، ولا تخمّن دخلاً أو رصيداً أو موعد راتب.
- remaining_inside_categories هو مجموع أرصدة مقيدة داخل بنود مختلفة، وليس نقداً حراً ولا يجوز جمعه كمبلغ متاح للإنفاق.
- protected_savings_remaining ادخار محمي؛ وصول بند الادخار إلى 100% نجاح للخطة وليس إنذاراً.
- الرصيد المرحّل داخل كل بند جزء من ميزانيته الفعلية الحالية ويجب أخذه في الاعتبار عند ذكر النسبة أو المتبقي.
- لا تقترح نقل فائض بند إلى بند آخر أو تغطية العجز من بند آخر؛ تسوية التجاوزات يقررها النظام عند إغلاق الدورة حسب الإعدادات.
- لا تصف الأسبوع بأنه الأخير إلا إذا كانت is_final_week تساوي true.
- البنود الثابتة عند 100% لا تذكر كحالة عاجلة إلا عند تجاوزها. ركّز على المتجاوز أو القريب من الحد أو ذو الحصة الأسبوعية المتجاوزة.
- لا تنصح بتأجيل علاج أو دواء ضروري؛ يمكنك فقط اقتراح تجنب المصروف الصحي غير العاجل عند الحاجة.
- إن وجدت عمليات غير مصنفة أو غير مخصصة، اذكرها كتنبيه جودة بيانات قصير فقط إذا كان عددها أو مبلغها أكبر من صفر.
- عند وجود remaining سالب، اكتب «تجاوز X ريال» ولا تعرضه أبداً بصيغة «المتبقي -X».
- لا تضع بنداً عند 100% فقط ضمن «الأولويات الآن» إلا إذا تجاوز الحد أو تجاوز حصته الأسبوعية أو كان المتبقي صفراً مع حاجة واقعية للإنفاق فيه.
- لا تصف فائض بند مثل المناسبات أو الادخار أو الوقود بأنه احتياطي عام، ولا تقترح استخدامه لتغطية احتياج بند مختلف. يمكن فقط تذكير المستخدم بالالتزام بغرض كل بند.
- اجعل درجة اللغة متناسبة مع البيانات: استخدم «حذر» أو «تقليل» قبل اللجوء لعبارات مثل «تجميد» أو «حرج».

صيغة الإجابة حرفياً بهذا الترتيب، من دون جداول أو Markdown:
📊 تقريرك المالي — اكتب رقم الأسبوع وعدد الأسابيع الفعليين من بيانات cycle.

1️⃣ الوضع العام
فقرة واحدة من جملتين كحد أقصى، مبنية على الحقائق فقط.

2️⃣ الأولويات الآن
من 2 إلى 5 نقاط فقط. اذكر اسم البند والرقم أو النسبة التي تبرر التنبيه. لا تذكر بند الادخار كأولوية سلبية.

3️⃣ خطة الأيام القادمة
3 نقاط عملية، مرتبطة مباشرة بأكثر البنود احتياجاً للحذر.

4️⃣ للدورة القادمة
نقطة أو نقطتان فقط، واقتراحهما مبني على التجاوزات أو نمط الإنفاق الظاهر.

اجعل اللغة ودية وحاسمة، وتجنب عبارات عامة مثل «الوضع حرج» ما لم توجد تجاوزات فعلية أو مؤشرات مالية واضحة. لا تذكر JSON أو قواعدك أو تفاصيل تقنية.

إن كانت cycle_closure_rules.auto_settle_overages مفعلة، يمكنك الإشارة باختصار إلى أن تسوية العجز تتم تلقائياً من بند المصدر عند الإغلاق، من دون اقتراح تحويلات يدوية بين البنود.

بيانات التقرير:
{$data}
PROMPT;

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                ]
            );

            Log::info('Gemini advice response', ['status' => $response->status(), 'body' => $response->json()]);

            $text = $response->json('candidates.0.content.parts.0.text');
            if ($text) {
                $this->telegram->sendMessage($text);
            } else {
                $this->telegram->sendMessage('⚠️ لم أتمكن من تحليل البيانات: ' . json_encode($response->json(), JSON_UNESCAPED_UNICODE));
            }
        } catch (\Exception $e) {
            Log::error('Advice AI error: ' . $e->getMessage());
            $this->telegram->sendMessage('⚠️ حدث خطأ أثناء التحليل');
        }
    }
}
