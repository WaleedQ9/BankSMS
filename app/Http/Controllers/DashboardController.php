<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\PendingSalaryConfirmation;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use App\Services\BudgetService;

class DashboardController extends Controller
{
    public function __construct(
        private BillingCycleService $cycleService,
        private BudgetService $budgetService,
    ) {}

    public function index()
    {
        $cycle = $this->cycleService->getCurrentCycle();
        $week = $this->cycleService->getCurrentWeek();

        // Weekly & monthly totals (expenses only)
        $weeklyTotal = Transaction::where('week_id', $week->id)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->sum('amount');

        $monthlyTotal = Transaction::where('cycle_id', $cycle->id)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->sum('amount');

        // Categories with budgets
        $budgets = Budget::with('category')->where('monthly_amount', '>', 0)->get();
        $weeklyStats = [];
        $monthlyStats = [];
        $totalOverages = 0;
        $overageCategoriesCount = 0;
        $savingsCategoryId = (int) Setting::getValue('savings_category_id', '0');
        $overageSourceCategoryId = (int) Setting::getValue('overage_source_category_id', '0');
        $autoSettleOverages = Setting::getValue('auto_settle_overages', '0') === '1';
        $overageItems = [];

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            $weeklySpent = $this->budgetService->getWeeklySpent($budget->category_id, $week->id);
            $cycleSpent = $this->budgetService->getCycleSpent($budget->category_id, $cycle->id);
            $carried = (float) ($cat->carried_balance ?? 0);
            $effectiveBudget = (float) $budget->monthly_amount + $carried;
            $monthlyPct = $effectiveBudget > 0 ? min(($cycleSpent / $effectiveBudget) * 100, 150) : 0;
            $monthlyOverage = max(0, $cycleSpent - $effectiveBudget);
            // Savings is a transfer to the second account, not a spending overage.
            if ($monthlyOverage > 0 && $cat->id !== $savingsCategoryId && $cat->id !== $overageSourceCategoryId) {
                $totalOverages += $monthlyOverage;
                $overageCategoriesCount++;
                $overageItems[] = [
                    'name' => $cat->name,
                    'icon' => $cat->icon,
                    'amount' => $monthlyOverage,
                ];
            }

            // Weekly stats: show spent / remaining at start of week
// Weekly stats
if ($cat->show_in_weekly) {
    $weeklyAllowance = $this->budgetService->getWeeklyAllowance(
        $budget->category_id, 
        $cycle, 
        $week
    );
    
    if ($weeklyAllowance !== null && $weeklyAllowance > 0) {
        $weeklyPct = min(($weeklySpent / $weeklyAllowance) * 100, 150);
        $weeklyStats[] = [
            'category'   => $cat,
            'allowance'  => $weeklyAllowance,
            'spent'      => $weeklySpent,
            'percentage' => round($weeklyPct),
        ];
    } elseif ($weeklyAllowance !== null) {
        // ميزانية الشهر انتهت
        $weeklyStats[] = [
            'category'   => $cat,
            'allowance'  => 0,
            'spent'      => $weeklySpent,
            'percentage' => $weeklySpent > 0 ? 150 : 0,
        ];
    }
}

            // Monthly stats (all categories)
            $monthlyStats[] = [
                'category' => $cat,
                'budget' => $budget->monthly_amount,
                'carried' => $carried,
                'effective_budget' => $effectiveBudget,
                'spent' => $cycleSpent,
                'percentage' => round($monthlyPct),
            ];
        }

        $weeklyAllowanceTotal = array_sum(array_column($weeklyStats, 'allowance'));

        $overageSource = null;
        $overageCoverage = 0;
        $overageUncovered = $totalOverages;
        $overageSourceRemainingBefore = null;
        $overageSourceRemainingAfter = null;
        if ($totalOverages > 0 && $autoSettleOverages && $overageSourceCategoryId) {
            $sourceBudget = $budgets->firstWhere('category_id', $overageSourceCategoryId);
            $overageSource = $sourceBudget?->category;
            if ($sourceBudget && $overageSource) {
                $sourceSpent = $this->budgetService->getCycleSpent($overageSource->id, $cycle->id);
                $sourceEffectiveBudget = (float) $sourceBudget->monthly_amount + (float) $overageSource->carried_balance;
                $overageSourceRemainingBefore = max(0, $sourceEffectiveBudget - $sourceSpent);
                $overageCoverage = min($totalOverages, $overageSourceRemainingBefore);
                $overageUncovered = $totalOverages - $overageCoverage;
                $overageSourceRemainingAfter = $overageSourceRemainingBefore - $overageCoverage;
            }
        }

        // Days info
        $today = now()->startOfDay();
        $weekDaysPassed = (int) $week->start_date->diffInDays($today, false) + 1;
        $weekTotalDays = (int) $week->start_date->diffInDays($week->end_date, false) + 1;
        $weekDaysLeft = max(0, (int) $today->diffInDays($week->end_date, false));
        $expectedSalaryDate = $this->cycleService->getExpectedSalaryDate($cycle);
        $cycleDaysLeft = max(0, (int) $today->diffInDays($expectedSalaryDate, false));

        // Transaction count & income
        $transactionCount = Transaction::where('cycle_id', $cycle->id)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->count();

        $incomeTotal = Transaction::where('cycle_id', $cycle->id)
            ->where('type', 'income')
            ->sum('amount');

        $pendingSalaryConfirmation = PendingSalaryConfirmation::where('status', 'pending')
            ->oldest('transaction_date')
            ->first();

        // Last 5 transactions
        $recentTransactions = Transaction::with('category')
            ->orderByDesc('transaction_date')
            ->limit(5)
            ->get();

        return view('dashboard.index', compact(
            'cycle',
            'week',
            'weeklyTotal',
            'monthlyTotal',
            'weeklyAllowanceTotal',
            'weeklyStats',
            'monthlyStats',
            'recentTransactions',
            'transactionCount',
            'incomeTotal',
            'pendingSalaryConfirmation',
            'weekDaysPassed',
            'weekTotalDays',
            'weekDaysLeft',
            'cycleDaysLeft',
            'expectedSalaryDate',
            'totalOverages',
            'overageCategoriesCount',
            'overageItems',
            'autoSettleOverages',
            'overageSource',
            'overageCoverage',
            'overageUncovered',
            'overageSourceRemainingBefore',
            'overageSourceRemainingAfter'
        ));
    }
}
