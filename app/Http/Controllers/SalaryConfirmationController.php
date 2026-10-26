<?php

namespace App\Http\Controllers;

use App\Models\PendingSalaryConfirmation;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryConfirmationController extends Controller
{
    public function resolve(Request $request, PendingSalaryConfirmation $salaryConfirmation, BillingCycleService $cycleService)
    {
        $request->validate(['decision' => 'required|in:start_cycle,record_income,ignore']);

        $decision = $request->string('decision')->toString();

        return DB::transaction(function () use ($salaryConfirmation, $decision, $cycleService) {
            $lockedConfirmation = PendingSalaryConfirmation::where('id', $salaryConfirmation->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedConfirmation || $lockedConfirmation->status !== 'pending') {
                return redirect()->route('dashboard')->with('success', 'تم التعامل مع رسالة الراتب هذه مسبقاً.');
            }

            if ($decision === 'ignore') {
                $lockedConfirmation->update(['status' => 'ignored', 'resolved_at' => now()]);
                return redirect()->route('dashboard')->with('success', 'تم تجاهل رسالة الراتب المحتملة.');
            }

            $cycle = $decision === 'start_cycle'
                ? $cycleService->startCycleOnSalary($lockedConfirmation->transaction_date)
                : $cycleService->getCurrentCycle();
            $week = $cycleService->getWeekForDate($cycle, $lockedConfirmation->transaction_date);

            Transaction::create([
                'cycle_id' => $cycle->id,
                'week_id' => $week->id,
                'category_id' => null,
                'type' => 'income',
                'amount' => $lockedConfirmation->amount,
                'merchant' => $lockedConfirmation->merchant ?: 'راتب',
                'card_last4' => $lockedConfirmation->card_last4,
                'payment_method' => $lockedConfirmation->payment_method,
                'transaction_date' => $lockedConfirmation->transaction_date,
                'sms_raw' => $lockedConfirmation->sms_raw,
                'is_classified' => true,
                'classified_at' => now(),
                'needs_reminder' => false,
            ]);

            $lockedConfirmation->update([
                'status' => $decision === 'start_cycle' ? 'started_cycle' : 'recorded_income',
                'resolved_at' => now(),
            ]);

            return redirect()->route('dashboard')->with(
                'success',
                $decision === 'start_cycle' ? 'تم فتح دورة جديدة وتسجيل الراتب كأول دخل.' : 'تم تسجيل المبلغ كدخل في الدورة الحالية.'
            );
        });
    }
}
