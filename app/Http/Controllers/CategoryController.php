<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetAlert;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('budget')->orderBy('id')->get();
        $monthlyIncome = (float) Setting::getValue('monthly_income', '0');
        $totalBudget = $categories->sum(fn($c) => $c->budget->monthly_amount ?? 0);
        $remaining = $monthlyIncome - $totalBudget;
        return view('categories.index', compact('categories', 'monthlyIncome', 'totalBudget', 'remaining'));
    }

    public function updateIncome(Request $request)
    {
        $request->validate(['monthly_income' => 'required|numeric|min:0']);
        Setting::setValue('monthly_income', $request->monthly_income);
        return back()->with('success', 'تم تحديث الدخل الشهري');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'icon' => 'required|string|max:10',
            'color' => 'required|string|max:7',
        ]);

        $category = Category::create([
            ...$request->only('name', 'icon', 'color'),
            'rollover_type' => $request->input('rollover_type', 'rollover'),
        ]);
        Budget::create([
            'category_id' => $category->id,
            'monthly_amount' => $request->input('monthly_amount', 0),
        ]);
        return back()->with('success', 'تمت إضافة الفئة');
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'icon' => 'required|string|max:10',
            'color' => 'required|string|max:7',
        ]);

        $category->update([
            ...$request->only('name', 'icon', 'color'),
            'show_in_weekly' => $request->has('show_in_weekly'),
            'rollover_type' => $request->input('rollover_type', $category->rollover_type),
        ]);
        Budget::updateOrCreate(
            ['category_id' => $category->id],
            ['monthly_amount' => $request->input('monthly_amount', 0)]
        );
        return back()->with('success', 'تم تحديث الفئة');
    }

    public function toggleActive(Category $category)
    {
        $category->update(['is_active' => !$category->is_active]);
        $status = $category->is_active ? 'مفعّلة' : 'معطّلة';
        return back()->with('success', "الفئة {$category->name} الآن {$status}");
    }

    public function destroy(Category $category, BillingCycleService $cycleService)
    {
        $name = $category->name;
        $cycle = $cycleService->getCurrentCycle();

        // Unclassify current cycle transactions only
        Transaction::where('category_id', $category->id)
            ->where('cycle_id', $cycle->id)
            ->update([
                'category_id' => null,
                'is_classified' => false,
                'classified_at' => null,
            ]);

        // Delete related records
        BudgetAlert::where('category_id', $category->id)->delete();
        Budget::where('category_id', $category->id)->delete();
        $category->delete();

        return back()->with('success', "تم حذف الفئة: {$name}");
    }
}
