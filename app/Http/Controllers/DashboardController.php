<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
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

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            $weeklySpent = $this->budgetService->getWeeklySpent($budget->category_id, $week->id);
            $cycleSpent = $this->budgetService->getCycleSpent($budget->category_id, $cycle->id);
            $monthlyPct = $budget->monthly_amount > 0 ? min(($cycleSpent / $budget->monthly_amount) * 100, 150) : 0;

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
                'spent' => $cycleSpent,
                'percentage' => round($monthlyPct),
            ];
        }

        $weeklyAllowanceTotal = array_sum(array_column($weeklyStats, 'allowance'));

        // Days info
        $today = now()->startOfDay();
        $weekDaysPassed = (int) $week->start_date->diffInDays($today, false) + 1;
        $weekTotalDays = (int) $week->start_date->diffInDays($week->end_date, false) + 1;
        $weekDaysLeft = max(0, (int) $today->diffInDays($week->end_date, false));
        $cycleDaysLeft = $cycle->end_date
            ? max(0, (int) $today->diffInDays($cycle->end_date, false))
            : null;

        // Transaction count & income
        $transactionCount = Transaction::where('cycle_id', $cycle->id)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->count();

        $incomeTotal = Transaction::where('cycle_id', $cycle->id)
            ->where('type', 'income')
            ->sum('amount');

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
            'weekDaysPassed',
            'weekTotalDays',
            'weekDaysLeft',
            'cycleDaysLeft'
        ));
    }
}
