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
        $text .= "الدورة: {$cycle->start_date->format('d/m')} - {$cycle->end_date->format('d/m')}\n";
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
        $budgets = Budget::with('category')->where('monthly_amount', '>', 0)->get();

        // Build data summary for AI
        $data = "بيانات المصاريف:\n";
        $data .= "الدورة: {$cycle->start_date->format('d/m')} - {$cycle->end_date->format('d/m')}\n";
        $data .= "الأسبوع الحالي: {$week->week_number} من 4\n";
        $data .= "أيام متبقية في الأسبوع: " . max(0, now()->diffInDays($week->end_date, false)) . "\n";
        $data .= "أيام متبقية في الدورة: " . max(0, now()->diffInDays($cycle->end_date, false)) . "\n\n";

        $totalSpent = 0;
        $totalBudget = 0;

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            $cycleSpent = $this->budgetService->getCycleSpent($cat->id, $cycle->id);
            $monthlyPct = $budget->monthly_amount > 0 ? round(($cycleSpent / $budget->monthly_amount) * 100) : 0;

            $data .= "{$cat->name}: صرف " . number_format($cycleSpent, 0) . " من " . number_format($budget->monthly_amount, 0) . " ريال ({$monthlyPct}%)\n";

            if ($cat->show_in_weekly) {
                $weeklySpent = $this->budgetService->getWeeklySpent($cat->id, $week->id);
                $weeklyAllowance = $this->budgetService->getWeeklyAllowance($cat->id, $cycle, $week);
                if ($weeklyAllowance !== null && $weeklyAllowance > 0) {
                    $data .= "  الأسبوعي: " . number_format($weeklySpent, 0) . " / " . number_format($weeklyAllowance, 0) . " ريال\n";
                }
            }

            $totalSpent += $cycleSpent;
            $totalBudget += $budget->monthly_amount;
        }

        $data .= "\nإجمالي المصاريف: " . number_format($totalSpent, 0) . " ريال";
        $data .= "\nإجمالي الميزانيات: " . number_format($totalBudget, 0) . " ريال";
        $data .= "\nالمتبقي: " . number_format($totalBudget - $totalSpent, 0) . " ريال";

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
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}",
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
