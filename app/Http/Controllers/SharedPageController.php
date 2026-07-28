<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Setting;
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

        $categoryIds = json_decode(Setting::getValue('shared_categories', '[]'), true);
        if (empty($categoryIds)) {
            return view('shared.index', ['categories' => collect(), 'cycle' => null, 'week' => null]);
        }

        $cycle = $cycleService->getCurrentCycle();
        $week  = $cycleService->getCurrentWeek();

        $categories = Category::whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->get()
            ->map(function ($cat) use ($cycle, $week, $budgetService) {
                $budget        = Budget::where('category_id', $cat->id)->first();
                $monthlyBudget = $budget ? $budget->monthly_amount : 0;
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
                    'name'              => $cat->name,
                    'icon'              => $cat->icon,
                    'color'             => $cat->color,
                    'monthly_budget'    => $monthlyBudget,
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
            'weekDaysPassed', 'weekTotalDays', 'weekDaysLeft'
        ));
    }
}