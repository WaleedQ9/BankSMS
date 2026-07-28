<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetAlert;
use App\Models\BillingCycle;
use App\Models\BillingWeek;
use App\Models\Transaction;

class BudgetService
{
    public function __construct(
        private TelegramService $telegram,
    ) {}

    /**
     * الحصة الأسبوعية = المتبقي من الشهر ÷ الأسابيع الباقية
     * المتبقي = الميزانية الشهرية - ما صُرف في الأسابيع السابقة فقط
     */
    public function getWeeklyAllowance(int $categoryId, BillingCycle $cycle, BillingWeek $currentWeek): ?float
    {
        $budget = Budget::where('category_id', $categoryId)->first();
        if (!$budget) {
            return null;
        }

        // المصروف في الأسابيع السابقة فقط (قبل الأسبوع الحالي)
        $spentBefore = Transaction::where('cycle_id', $cycle->id)
            ->where('category_id', $categoryId)
            ->where('is_classified', true)
            ->whereHas('week', fn($q) => $q->where('week_number', '<', $currentWeek->week_number))
            ->sum('amount');

        $remaining  = $budget->monthly_amount - $spentBefore;
        $weeksLeft  = $cycle->weeks()
            ->where('week_number', '>=', $currentWeek->week_number)
            ->count();

        if ($weeksLeft <= 0) {
            return max(0, $remaining);
        }

        return max(0, $remaining / $weeksLeft);
    }

    /**
     * إجمالي المصروف في أسبوع معين لفئة معينة
     */
    public function getWeeklySpent(int $categoryId, int $weekId): float
    {
        return Transaction::where('week_id', $weekId)
            ->where('category_id', $categoryId)
            ->where('is_classified', true)
            ->sum('amount');
    }

    /**
     * إجمالي المصروف في الدورة كاملة لفئة معينة
     */
    public function getCycleSpent(int $categoryId, int $cycleId): float
    {
        return Transaction::where('cycle_id', $cycleId)
            ->where('category_id', $categoryId)
            ->where('is_classified', true)
            ->sum('amount');
    }

    /**
     * تحقق من الميزانية وأرسل تنبيه إذا لزم
     */
    public function checkAndAlert(Transaction $transaction): void
    {
        $budget = Budget::where('category_id', $transaction->category_id)->first();
        if (!$budget) {
            return;
        }

        $cycle      = $transaction->cycle;
        $week       = $transaction->week;
        $categoryId = $transaction->category_id;
        $category   = $transaction->category;

        // الحصة الأسبوعية المحسوبة بشكل صحيح
        $weeklyAllowance = $this->getWeeklyAllowance($categoryId, $cycle, $week);
        if (!$weeklyAllowance || $weeklyAllowance <= 0) {
            return;
        }

        $weeklySpent     = $this->getWeeklySpent($categoryId, $week->id);
        $cycleSpent      = $this->getCycleSpent($categoryId, $cycle->id);
        $monthlyRemaining = $budget->monthly_amount - $cycleSpent;
        $percentage      = ($weeklySpent / $weeklyAllowance) * 100;

        // تنبيه تجاوز 100%
        if ($percentage >= 100) {
            $alreadySent = BudgetAlert::where('category_id', $categoryId)
                ->where('week_id', $week->id)
                ->where('alert_type', 'exceeded_100')
                ->exists();

            if (!$alreadySent) {
                $text  = "🔴 تجاوزت الحصة الأسبوعية\n";
                $text .= "━━━━━━━━━━━━━\n";
                $text .= "تجاوزت حصة {$category->icon} {$category->name} هذا الأسبوع\n";
                $text .= "صرفت: " . number_format($weeklySpent, 2) . " من " . number_format($weeklyAllowance, 2) . " ريال\n";
                $text .= "المتبقي للشهر: " . number_format(max(0, $monthlyRemaining), 2) . " ريال";

                $this->telegram->sendMessage($text);

                BudgetAlert::create([
                    'category_id' => $categoryId,
                    'week_id'     => $week->id,
                    'alert_type'  => 'exceeded_100',
                    'sent_at'     => now(),
                ]);
            }
        }
        // تنبيه 80%
        elseif ($percentage >= 80) {
            $alreadySent = BudgetAlert::where('category_id', $categoryId)
                ->where('week_id', $week->id)
                ->where('alert_type', 'warning_80')
                ->exists();

            if (!$alreadySent) {
                $weeklyRemaining = $weeklyAllowance - $weeklySpent;

                $text  = "⚠️ تنبيه ميزانية\n";
                $text .= "━━━━━━━━━━━━━\n";
                $text .= "وصلت لـ " . round($percentage) . "% من حصة {$category->icon} {$category->name} هذا الأسبوع\n";
                $text .= "صرفت: " . number_format($weeklySpent, 2) . " من " . number_format($weeklyAllowance, 2) . " ريال\n";
                $text .= "المتبقي للأسبوع: " . number_format(max(0, $weeklyRemaining), 2) . " ريال\n";
                $text .= "المتبقي للشهر: " . number_format(max(0, $monthlyRemaining), 2) . " ريال";

                $this->telegram->sendMessage($text);

                BudgetAlert::create([
                    'category_id' => $categoryId,
                    'week_id'     => $week->id,
                    'alert_type'  => 'warning_80',
                    'sent_at'     => now(),
                ]);
            }
        }
    }
}