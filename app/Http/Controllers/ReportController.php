<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BillingCycleService;

class ReportController extends Controller
{
    public function __construct(
        private BillingCycleService $cycleService,
    ) {}

    public function index()
    {
        $cycle = $this->cycleService->getCurrentCycle();
        $weeks = $cycle->weeks()->orderBy('week_number')->get();
        $categories = Category::where('is_active', true)->get();

        // Weekly comparison data
        $weeklyData = [];
        foreach ($weeks as $week) {
            $total = Transaction::where('week_id', $week->id)
                ->whereIn('type', ['purchase', 'transfer', 'atm'])
                ->where('is_classified', true)
                ->sum('amount');
            $weeklyData[] = [
                'label' => 'الأسبوع ' . $week->week_number,
                'total' => round($total, 2),
            ];
        }

        // Monthly pie chart - by category
        $categoryData = [];
        foreach ($categories as $cat) {
            $spent = Transaction::where('cycle_id', $cycle->id)
                ->where('category_id', $cat->id)
                ->where('is_classified', true)
                ->sum('amount');
            if ($spent > 0) {
                $categoryData[] = [
                    'label' => $cat->icon . ' ' . $cat->name,
                    'amount' => round($spent, 2),
                    'color' => $cat->color,
                ];
            }
        }

        // Last 3 cycles comparison
        $cycles = BillingCycle::orderByDesc('start_date')->limit(3)->get();
        $cycleComparison = [];
        foreach ($cycles as $c) {
            $total = Transaction::where('cycle_id', $c->id)
                ->whereIn('type', ['purchase', 'transfer', 'atm'])
                ->sum('amount');
            $cycleComparison[] = [
                'label' => $c->start_date->format('M Y'),
                'total' => round($total, 2),
            ];
        }
        $cycleComparison = array_reverse($cycleComparison);

        return view('reports.index', compact(
            'cycle', 'weeklyData', 'categoryData', 'cycleComparison'
        ));
    }
}
