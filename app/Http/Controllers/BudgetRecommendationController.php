<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetRecommendation;

class BudgetRecommendationController extends Controller
{
    public function apply(BudgetRecommendation $budgetRecommendation)
    {
        if ($budgetRecommendation->applied_at) {
            return back()->with('error', 'تم تطبيق هذا الاقتراح مسبقاً.');
        }

        foreach ($budgetRecommendation->recommendations as $recommendation) {
            Budget::where('category_id', (int) $recommendation['category_id'])
                ->update(['monthly_amount' => (float) $recommendation['amount']]);
        }

        $budgetRecommendation->update(['applied_at' => now()]);

        return back()->with('success', 'تم تطبيق اقتراحات الميزانية للدورة القادمة.');
    }

    public function dismiss(BudgetRecommendation $budgetRecommendation)
    {
        if (!$budgetRecommendation->applied_at) {
            $budgetRecommendation->update(['applied_at' => now()]);
        }

        return back()->with('success', 'تم إخفاء الاقتراح. يمكنك دائماً تعديل الميزانيات يدوياً من البنود.');
    }
}
