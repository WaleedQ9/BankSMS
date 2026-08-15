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
            $carried = (float) ($cat->carried_balance ?? 0);
            $effectiveBudget = (float) $budget->monthly_amount + $carried;
            $monthlyPct = $effectiveBudget > 0 ? min(($cycleSpent / $effectiveBudget) * 100, 150) : 0;

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

        // Monthly calendar for the current cycle: daily total and largest expense.
        $calendarMonth = now()->startOfMonth();
        $calendarTransactions = Transaction::with('category')
            ->where('cycle_id', $cycle->id)
            ->whereBetween('transaction_date', [$calendarMonth, $calendarMonth->copy()->endOfMonth()->endOfDay()])
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->orderByDesc('amount')
            ->get()
            ->groupBy(fn (Transaction $transaction) => $transaction->transaction_date->toDateString());
        $calendarDays = array_fill(0, $calendarMonth->dayOfWeek, null);
        for ($day = 1; $day <= $calendarMonth->daysInMonth; $day++) {
            $date = $calendarMonth->copy()->day($day)->toDateString();
            $transactions = $calendarTransactions->get($date, collect());
            $largest = $transactions->first();
            $calendarDays[] = [
                'date' => $date, 'day' => $day, 'total' => (float) $transactions->sum('amount'),
                'largest' => $largest ? (($largest->category?->icon ?: '💳').' '.($largest->merchant ?: 'عملية')) : null,
            ];
        }
        $calendarTransactionsData = $calendarTransactions->map(fn ($transactions) => $transactions->sortByDesc('transaction_date')->values()->map(fn (Transaction $transaction) => [
            'merchant' => $transaction->merchant ?: 'عملية',
            'amount' => number_format((float) $transaction->amount, 2),
            'time' => $transaction->transaction_date->format('H:i'),
            'icon' => $transaction->category?->icon ?: '💳',
        ]));

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
            'cycleDaysLeft',
            'calendarMonth', 'calendarDays', 'calendarTransactionsData'
        ));
    }
}
