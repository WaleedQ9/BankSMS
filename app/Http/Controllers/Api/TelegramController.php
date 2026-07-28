<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use App\Services\BudgetService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
