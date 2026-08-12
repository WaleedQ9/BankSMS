<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\Budget;
use App\Models\CycleSnapshot;
use App\Models\Setting;
use App\Models\SavingsTransfer;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function index(Request $request, BillingCycleService $cycleService)
    {
        $cycles = BillingCycle::orderByDesc('start_date')->get();
        $savingsHistory = SavingsTransfer::with('cycle')
            ->orderByDesc('cycle_id')
            ->take(3)
            ->get();
        $savingsTotal = (float) SavingsTransfer::sum('amount');
        $savingsStartedAt = $savingsHistory->last()?->cycle?->start_date;
        $selectedCycleId = $request->query('cycle');

        if ($selectedCycleId) {
            $cycle = BillingCycle::findOrFail($selectedCycleId);
        } else {
            $cycle = $cycleService->getCurrentCycle();
        }

        // Check if this cycle has an archive
        $snapshots = CycleSnapshot::where('cycle_id', $cycle->id)->get();
        $isArchived = $snapshots->isNotEmpty();

        if ($isArchived) {
            return $this->showArchived($cycle, $cycles, $snapshots, $savingsHistory, $savingsTotal, $savingsStartedAt);
        }

        return $this->showLive($cycle, $cycles, $savingsHistory, $savingsTotal, $savingsStartedAt);
    }

    private function showLive(BillingCycle $cycle, $cycles, $savingsHistory, float $savingsTotal, $savingsStartedAt)
    {
        $monthlyIncome = (float) Setting::getValue('monthly_income', '0');
        $budgets = Budget::with('category')->get();
        $items = [];
        $totalBudget = 0;
        $totalSpent = 0;
        $totalRemaining = 0;

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            if (!$cat) continue;

            $spent = Transaction::where('cycle_id', $cycle->id)
                ->where('category_id', $cat->id)
                ->where('is_classified', true)
                ->sum('amount');

            $carried = (float) ($cat->carried_balance ?? 0);
            $effectiveBudget = $budget->monthly_amount + $carried;
            $remaining = $effectiveBudget - $spent;
            $percentage = $effectiveBudget > 0
                ? round(($spent / $effectiveBudget) * 100) : 0;

            $items[] = [
                'id' => $cat->id,
                'icon' => $cat->icon,
                'name' => $cat->name,
                'color' => $cat->color,
                'budget' => $budget->monthly_amount,
                'carried' => $carried,
                'effective_budget' => $effectiveBudget,
                'spent' => $spent,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'exceeded' => $remaining < 0,
            ];

            $totalBudget += $effectiveBudget;
            $totalSpent += $spent;
            $totalRemaining += max(0, $remaining);
        }

        $incomeTotal = Transaction::where('cycle_id', $cycle->id)
            ->where('type', 'income')->sum('amount');
        $availableAfterBudgets = $incomeTotal - $totalBudget;
        $transactionCount = Transaction::where('cycle_id', $cycle->id)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])->count();

        $unbudgetedSpent = Transaction::where('cycle_id', $cycle->id)
            ->where('is_classified', true)
            ->whereNotIn('category_id', $budgets->pluck('category_id')->filter())
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->sum('amount');

        $unclassifiedSpent = Transaction::where('cycle_id', $cycle->id)
            ->where('is_classified', false)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->sum('amount');

        // Comparison data: last 3 cycles
        $comparisonData = $this->getComparisonData($cycles->take(3));

        // Pie chart: current cycle category distribution
        $pieData = collect($items)->filter(fn($i) => $i['spent'] > 0)->values();

        return view('summary.index', compact(
            'cycle', 'cycles', 'items', 'monthlyIncome',
            'totalBudget', 'totalSpent', 'totalRemaining',
            'incomeTotal', 'availableAfterBudgets', 'transactionCount',
            'unbudgetedSpent', 'unclassifiedSpent',
            'comparisonData', 'pieData'
        ))->with('isArchived', false)
          ->with(compact('savingsHistory', 'savingsTotal', 'savingsStartedAt'));
    }

    private function showArchived(BillingCycle $cycle, $cycles, $snapshots, $savingsHistory, float $savingsTotal, $savingsStartedAt)
    {
        $summaryRow = $snapshots->firstWhere('category_name', '__summary__');
        $categoryRows = $snapshots->where('category_name', '!=', '__summary__');

        $items = [];
        $totalBudget = 0;
        $totalSpent = 0;
        $totalRemaining = 0;

        foreach ($categoryRows as $row) {
            $percentage = $row->budget_amount > 0
                ? round(($row->spent_amount / $row->budget_amount) * 100) : 0;

            $items[] = [
                'icon' => $row->category_icon,
                'name' => $row->category_name,
                'color' => $row->category_color,
                'budget' => $row->budget_amount,
                'spent' => $row->spent_amount,
                'remaining' => $row->remaining_amount,
                'percentage' => $percentage,
                'exceeded' => $row->remaining_amount < 0,
            ];

            $totalBudget += $row->budget_amount;
            $totalSpent += $row->spent_amount;
            $totalRemaining += max(0, $row->remaining_amount);
        }

        $monthlyIncome = (float) Setting::getValue('monthly_income', '0');
        $incomeTotal = $summaryRow->income_total ?? 0;
        $transactionCount = $summaryRow->transaction_count ?? 0;
        $availableAfterBudgets = $incomeTotal - $totalBudget;

        $comparisonData = $this->getComparisonData($cycles->take(3));
        $pieData = collect($items)->filter(fn($i) => $i['spent'] > 0)->values();

        return view('summary.index', compact(
            'cycle', 'cycles', 'items', 'monthlyIncome',
            'totalBudget', 'totalSpent', 'totalRemaining',
            'incomeTotal', 'availableAfterBudgets', 'transactionCount',
            'comparisonData', 'pieData'
        ))->with('isArchived', true)
          ->with('unbudgetedSpent', 0)
          ->with('unclassifiedSpent', 0)
          ->with(compact('savingsHistory', 'savingsTotal', 'savingsStartedAt'));
    }

    private function getComparisonData($cycles)
    {
        $data = [];
        foreach ($cycles as $c) {
            $spent = Transaction::where('cycle_id', $c->id)
                ->whereIn('type', ['purchase', 'transfer', 'atm'])
                ->sum('amount');

            $data[] = [
                'label' => $c->start_date->format('j/n/Y') . ' - ' . ($c->end_date?->format('j/n/Y') ?? 'الراتب القادم'),
                'spent' => round($spent),
            ];
        }
        return collect($data)->reverse()->values();
    }

    public function categoryTransactions(Request $request, int $categoryId)
    {
        $cycleId = $request->query('cycle');
        $transactions = Transaction::where('cycle_id', $cycleId)
            ->where('category_id', $categoryId)
            ->where('is_classified', true)
            ->orderByDesc('transaction_date')
            ->get(['merchant', 'amount', 'transaction_date', 'type']);

        return response()->json($transactions);
    }

    public function archive(BillingCycleService $cycleService)
    {
        return back()->with('success', 'تُغلق الدورة تلقائياً عند وصول رسالة الراتب التالية.');
    }
}
