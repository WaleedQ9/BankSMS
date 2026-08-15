<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Category;
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
        $data .= "الأسبوع الحالي: {$week->week_number} من 4\n";
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

            if ($cat->show_in_weekly) {
                $weeklySpent = $this->budgetService->getWeeklySpent($cat->id, $week->id);
                $weeklyAllowance = $this->budgetService->getWeeklyAllowance($cat->id, $cycle, $week);
                if ($weeklyAllowance !== null && $weeklyAllowance > 0) {
                    $data .= "  الأسبوعي: " . number_format($weeklySpent, 0) . " / " . number_format($weeklyAllowance, 0) . " ريال\n";
                }
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
