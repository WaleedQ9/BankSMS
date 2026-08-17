<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class SharedPageController extends Controller
{
    public function login()
    {
        if (session('shared_verified')) {
            return redirect()->route('shared.show');
        }
        return view('shared.login');
    }

    public function verify(Request $request)
    {
        $request->validate(['pin' => 'required|string']);

        $storedPin = Setting::getValue('shared_pin', '');

        if (empty($storedPin) || $request->input('pin') !== $storedPin) {
            return back()->with('error', 'الرمز غير صحيح');
        }

        session(['shared_verified' => true]);
        return redirect()->route('shared.show');
    }

    public function show(BillingCycleService $cycleService, BudgetService $budgetService)
    {
        if (!session('shared_verified')) {
            return redirect()->route('shared.login');
        }

        $cycle = $cycleService->getCurrentCycle();
        $week  = $cycleService->getCurrentWeek();
        $categoryIds = json_decode(Setting::getValue('shared_categories', '[]'), true);
        $transactionsLimit = (int) Setting::getValue('shared_transactions_limit', '0');

        $savingsCategoryId = (int) Setting::getValue('savings_category_id', '0');
        $overageSourceCategoryId = (int) Setting::getValue('overage_source_category_id', '0');
        $autoSettleOverages = Setting::getValue('auto_settle_overages', '0') === '1';
        $totalOverages = 0;
        $overageItems = [];

        $allBudgets = Budget::with('category')
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get();

        foreach ($allBudgets as $budget) {
            $category = $budget->category;
            $effectiveBudget = (float) $budget->monthly_amount + (float) ($category->carried_balance ?? 0);
            $spent = $budgetService->getCycleSpent($category->id, $cycle->id);
            $overage = max(0, $spent - $effectiveBudget);

            // الادخار تحويل بين الحسابات، وبند مصدر التسوية لا يحتسب كتجاوز مستقل.
            if ($overage > 0 && !in_array($category->id, [$savingsCategoryId, $overageSourceCategoryId], true)) {
                $totalOverages += $overage;
                $overageItems[] = (object) [
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'amount' => $overage,
                ];
            }
        }

        $overageSource = null;
        $overageCoverage = 0;
        $overageUncovered = $totalOverages;
        $overageSourceRemainingAfter = null;

        if ($totalOverages > 0 && $autoSettleOverages && $overageSourceCategoryId) {
            $sourceBudget = $allBudgets->firstWhere('category_id', $overageSourceCategoryId);
            $overageSource = $sourceBudget?->category;

            if ($sourceBudget && $overageSource) {
                $sourceEffectiveBudget = (float) $sourceBudget->monthly_amount + (float) ($overageSource->carried_balance ?? 0);
                $sourceSpent = $budgetService->getCycleSpent($overageSource->id, $cycle->id);
                $sourceRemaining = max(0, $sourceEffectiveBudget - $sourceSpent);
                $overageCoverage = min($totalOverages, $sourceRemaining);
                $overageUncovered = $totalOverages - $overageCoverage;
                $overageSourceRemainingAfter = $sourceRemaining - $overageCoverage;
            }
        }

        $categories = Category::whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->get()
            ->map(function ($cat) use ($cycle, $week, $budgetService) {
                $budget        = Budget::where('category_id', $cat->id)->first();
                $baseBudget    = $budget ? (float) $budget->monthly_amount : 0;
                $carried       = (float) ($cat->carried_balance ?? 0);
                $monthlyBudget = $baseBudget + $carried;
                $monthlySpent  = $budgetService->getCycleSpent($cat->id, $cycle->id);
                $monthlyRemaining = $monthlyBudget - $monthlySpent;
                $monthlyPercent   = $monthlyBudget > 0
                    ? min(($monthlySpent / $monthlyBudget) * 100, 150)
                    : 0;

                $weeklyAllowance = null;
                $weeklySpent     = null;
                $weeklyRemaining = null;
                $weeklyPercent   = null;

                if ($cat->show_in_weekly && $budget) {
                    $weeklySpent     = $budgetService->getWeeklySpent($cat->id, $week->id);
                    $weeklyAllowance = $budgetService->getWeeklyAllowance($cat->id, $cycle, $week);

                    if ($weeklyAllowance !== null && $weeklyAllowance > 0) {
                        $weeklyRemaining = $weeklyAllowance - $weeklySpent;
                        $weeklyPercent   = min(($weeklySpent / $weeklyAllowance) * 100, 150);
                    }
                }

                return (object) [
                    'id'                => $cat->id,
                    'name'              => $cat->name,
                    'icon'              => $cat->icon,
                    'color'             => $cat->color,
                    'base_budget'       => $baseBudget,
                    'carried_balance'   => $carried,
                    'monthly_budget'    => $monthlyBudget,
                    'has_monthly'       => $monthlyBudget > 0,
                    'monthly_spent'     => $monthlySpent,
                    'monthly_remaining' => $monthlyRemaining,
                    'monthly_percent'   => round($monthlyPercent),
                    'has_weekly'        => $weeklyAllowance !== null && $weeklyAllowance > 0,
                    'weekly_allowance'  => $weeklyAllowance,
                    'weekly_spent'      => $weeklySpent,
                    'weekly_remaining'  => $weeklyRemaining,
                    'weekly_percent'    => $weeklyPercent !== null ? round($weeklyPercent) : null,
                ];
            });

        $today          = now()->startOfDay();
        $weekDaysPassed = (int) $week->start_date->diffInDays($today, false) + 1;
        $weekTotalDays  = (int) $week->start_date->diffInDays($week->end_date, false) + 1;
        $weekDaysLeft   = max(0, (int) $today->diffInDays($week->end_date, false));

        return view('shared.index', compact(
            'categories', 'cycle', 'week',
            'weekDaysPassed', 'weekTotalDays', 'weekDaysLeft', 'transactionsLimit',
            'totalOverages', 'overageItems', 'autoSettleOverages', 'overageSource',
            'overageCoverage', 'overageUncovered', 'overageSourceRemainingAfter'
        ));
    }

    public function categoryTransactions(Request $request, Category $category, BillingCycleService $cycleService)
    {
        abort_unless(session('shared_verified'), 403);

        $limit = (int) Setting::getValue('shared_transactions_limit', '0');
        abort_unless(in_array($limit, [3, 5, 10], true), 404);

        $sharedIds = json_decode(Setting::getValue('shared_categories', '[]'), true) ?: [];
        abort_unless(in_array($category->id, array_map('intval', $sharedIds), true), 404);

        $cycle = $cycleService->getCurrentCycle();
        $transactions = Transaction::where('cycle_id', $cycle->id)
            ->where('category_id', $category->id)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->orderByDesc('transaction_date')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'merchant' => $transaction->merchant ?: 'عملية',
                'amount' => number_format((float) $transaction->amount, 2),
                'date' => $transaction->transaction_date?->format('j/n/Y H:i'),
            ]);

        return response()->json(['transactions' => $transactions]);
    }
}
