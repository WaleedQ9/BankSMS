<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetAlert;
use App\Models\BillingCycle;
use App\Models\BillingWeek;
use App\Models\Category;
use App\Models\Transaction;

class BudgetService
{
    public function __construct(private TelegramService $telegram) {}

    /** حصة فترة الصرف من المتبقي، موزعة على الفترات المتبقية. */
    public function getWeeklyAllowance(int $categoryId, BillingCycle $cycle, BillingWeek $currentWeek): ?float
    {
        $budget = Budget::where('category_id', $categoryId)->first();
        if (!$budget) {
            return null;
        }

        $spentBefore = Transaction::where('cycle_id', $cycle->id)
            ->where('category_id', $categoryId)
            ->where('is_classified', true)
            ->whereHas('week', fn ($query) => $query->where('week_number', '<', $currentWeek->week_number))
            ->sum('amount');

        $effectiveBudget = (float) $budget->monthly_amount + (float) ($budget->category?->carried_balance ?? 0);
        $remaining = $effectiveBudget - $spentBefore;
        $weeksLeft = $cycle->weeks()
            ->where('week_number', '>=', $currentWeek->week_number)
            ->count();

        return $weeksLeft <= 0 ? max(0, $remaining) : max(0, $remaining / $weeksLeft);
    }

    public function getWeeklySpent(int $categoryId, int $weekId): float
    {
        return (float) Transaction::where('week_id', $weekId)
            ->where('category_id', $categoryId)
            ->where('is_classified', true)
            ->sum('amount');
    }

    public function getCycleSpent(int $categoryId, int $cycleId): float
    {
        return (float) Transaction::where('cycle_id', $cycleId)
            ->where('category_id', $categoryId)
            ->where('is_classified', true)
            ->sum('amount');
    }

    /** يرسل تنبيهات العتبات مرة واحدة فقط. */
    public function checkAndAlert(Transaction $transaction): void
    {
        $budget = Budget::where('category_id', $transaction->category_id)->first();
        $cycle = $transaction->cycle;
        $week = $transaction->week;
        $category = $transaction->category;

        if (!$budget || !$cycle || !$week || !$category) {
            return;
        }

        $cycleSpent = $this->getCycleSpent($category->id, $cycle->id);
        $effectiveBudget = (float) $budget->monthly_amount + (float) $category->carried_balance;
        if ($effectiveBudget <= 0) {
            return;
        }

        // كل البنود تتلقى تنبيهها الشهري، حتى إن لم تكن ضمن خطة الصرف.
        $monthlyPercentage = ($cycleSpent / $effectiveBudget) * 100;
        $this->sendMonthlyAlert(
            $category,
            $week,
            $cycleSpent,
            $effectiveBudget,
            $effectiveBudget - $cycleSpent,
            $monthlyPercentage,
        );

        // لا يوجد أي تنبيه للفترة للبند غير المعلّم ضمن خطة الصرف في إعداد البنود.
        if (!$category->show_in_weekly) {
            return;
        }

        $weeklyAllowance = $this->getWeeklyAllowance($category->id, $cycle, $week);
        if (!$weeklyAllowance || $weeklyAllowance <= 0) {
            return;
        }

        $weeklySpent = $this->getWeeklySpent($category->id, $week->id);
        $this->sendWeeklyAlert($category, $week, $weeklySpent, $weeklyAllowance, ($weeklySpent / $weeklyAllowance) * 100);
    }

    private function sendMonthlyAlert(Category $category, BillingWeek $week, float $spent, float $budget, float $remaining, float $percentage): void
    {
        $threshold = $percentage >= 100 ? 100 : ($percentage >= 80 ? 80 : ($percentage >= 60 ? 60 : null));
        if ($threshold === null) {
            return;
        }

        $alertType = "monthly_{$threshold}";
        $alreadySent = BudgetAlert::where('category_id', $category->id)
            ->where('alert_type', $alertType)
            ->whereHas('week', fn ($query) => $query->where('cycle_id', $week->cycle_id))
            ->exists();
        if ($alreadySent) {
            return;
        }

        $title = $threshold >= 100 ? '🔴 وصلت لحد الميزانية الشهرية' : '⚠️ تنبيه ميزانية شهرية';
        $text = "{$title}\n━━━━━━━━━━━━━\n";
        $text .= "وصلت إلى " . round($percentage) . "% من ميزانية {$category->icon} {$category->name}\n";
        $text .= 'صرفت: ' . number_format($spent, 2) . ' من ' . number_format($budget, 2) . " ريال\n";
        $text .= 'المتبقي للدورة: ' . number_format(max(0, $remaining), 2) . ' ريال';

        $this->telegram->sendMessage($text);
        BudgetAlert::create(['category_id' => $category->id, 'week_id' => $week->id, 'alert_type' => $alertType, 'sent_at' => now()]);
    }

    private function sendWeeklyAlert(Category $category, BillingWeek $week, float $spent, float $allowance, float $percentage): void
    {
        $threshold = $percentage >= 100 ? 100 : ($percentage >= 60 ? 60 : null);
        if ($threshold === null) {
            return;
        }

        $alertType = "weekly_{$threshold}";
        $alreadySent = BudgetAlert::where('category_id', $category->id)
            ->where('week_id', $week->id)
            ->where('alert_type', $alertType)
            ->exists();
        if ($alreadySent) {
            return;
        }

        $title = $threshold >= 100 ? '🔴 وصلت لحد حصة الفترة' : '⚠️ تنبيه خطة الصرف';
        $text = "{$title}\n━━━━━━━━━━━━━\n";
        $text .= "وصلت إلى " . round($percentage) . "% من حصة {$category->icon} {$category->name} في الفترة الحالية\n";
        $text .= 'صرفت: ' . number_format($spent, 2) . ' من ' . number_format($allowance, 2) . " ريال\n";
        $text .= 'المتبقي للفترة: ' . number_format(max(0, $allowance - $spent), 2) . ' ريال';

        $this->telegram->sendMessage($text);
        BudgetAlert::create(['category_id' => $category->id, 'week_id' => $week->id, 'alert_type' => $alertType, 'sent_at' => now()]);
    }
}
