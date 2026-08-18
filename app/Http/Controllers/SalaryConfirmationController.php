<?php

namespace App\Http\Controllers;

use App\Models\PendingSalaryConfirmation;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use Illuminate\Http\Request;

class SalaryConfirmationController extends Controller
{
    public function resolve(Request $request, PendingSalaryConfirmation $salaryConfirmation, BillingCycleService $cycleService)
    {
        $request->validate(['decision' => 'required|in:start_cycle,record_income,ignore']);

        if ($salaryConfirmation->status !== 'pending') {
            return redirect()->route('dashboard')->with('success', 'تم التعامل مع رسالة الراتب هذه مسبقاً.');
        }

        $decision = $request->string('decision')->toString();

        if ($decision === 'ignore') {
            $salaryConfirmation->update(['status' => 'ignored', 'resolved_at' => now()]);
            return redirect()->route('dashboard')->with('success', 'تم تجاهل رسالة الراتب المحتملة.');
        }

        $cycle = $decision === 'start_cycle'
            ? $cycleService->startCycleOnSalary($salaryConfirmation->transaction_date)
            : $cycleService->getCurrentCycle();
        $week = $cycleService->getWeekForDate($cycle, $salaryConfirmation->transaction_date);

        Transaction::create([
            'cycle_id' => $cycle->id,
            'week_id' => $week->id,
            'category_id' => null,
            'type' => 'income',
            'amount' => $salaryConfirmation->amount,
            'merchant' => $salaryConfirmation->merchant ?: 'راتب',
            'card_last4' => $salaryConfirmation->card_last4,
            'payment_method' => $salaryConfirmation->payment_method,
            'transaction_date' => $salaryConfirmation->transaction_date,
            'sms_raw' => $salaryConfirmation->sms_raw,
            'is_classified' => true,
            'classified_at' => now(),
            'needs_reminder' => false,
        ]);

        $salaryConfirmation->update([
            'status' => $decision === 'start_cycle' ? 'started_cycle' : 'recorded_income',
            'resolved_at' => now(),
        ]);

        return redirect()->route('dashboard')->with(
            'success',
            $decision === 'start_cycle' ? 'تم فتح دورة جديدة وتسجيل الراتب كأول دخل.' : 'تم تسجيل المبلغ كدخل في الدورة الحالية.'
        );
    }
}
