<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\BillingWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class BillingCycleService
{
    public function getCycleForDate(Carbon $date): BillingCycle
    {
        // Transactions in a past, closed period remain in that period.
        $closedCycle = BillingCycle::where('is_open', false)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($closedCycle) {
            return $closedCycle;
        }

        // No calendar date opens a cycle; ordinary transactions belong to the active cycle.
        return BillingCycle::where('is_open', true)->latest('start_date')->first()
            ?? $this->createOpenCycle($date);
    }

    /** Start a cycle only after the salary SMS has been received. */
    public function startCycleOnSalary(Carbon $salaryDate): BillingCycle
    {
        $openCycle = BillingCycle::where('is_open', true)->latest('start_date')->first();

        // Prevent duplicate delivery of the same salary SMS from starting another period.
        if ($openCycle && $openCycle->start_date->isSameDay($salaryDate)) {
            return $openCycle;
        }

        if ($openCycle) {
            DB::transaction(function () use ($openCycle, $salaryDate): void {
                $actualEnd = $salaryDate->copy()->subDay();
                $openCycle->update([
                    'end_date' => $actualEnd->toDateString(),
                    'is_open' => false,
                ]);

                $finalWeek = $openCycle->weeks()
                    ->where('start_date', '<=', $actualEnd->toDateString())
                    ->where('end_date', '>=', $actualEnd->toDateString())
                    ->first();

                if ($finalWeek) {
                    $finalWeek->update(['end_date' => $actualEnd->toDateString()]);
                    $openCycle->weeks()->where('week_number', '>', $finalWeek->week_number)->delete();
                }
            });

            // Archives the balances and applies each category's rollover/savings rule.
            Artisan::call('cycle:archive', ['--cycle' => $openCycle->id]);
        }

        return $this->createOpenCycle($salaryDate);
    }

    public function getWeekForDate(BillingCycle $cycle, Carbon $date): BillingWeek
    {
        return $cycle->weeks()
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first()
            ?? $cycle->weeks()->orderByDesc('week_number')->first();
    }

    public function getCurrentCycle(): BillingCycle
    {
        return BillingCycle::where('is_open', true)->latest('start_date')->first()
            ?? $this->getCycleForDate(Carbon::now());
    }

    public function getCurrentWeek(): BillingWeek
    {
        return $this->getWeekForDate($this->getCurrentCycle(), Carbon::now());
    }

    public function getRemainingWeeksCount(BillingCycle $cycle, BillingWeek $currentWeek): int
    {
        return $cycle->weeks()->where('week_number', '>=', $currentWeek->week_number)->count();
    }

    private function createOpenCycle(Carbon $startDate): BillingCycle
    {
        // A cycle has no end date until the next salary SMS arrives.
        $cycle = BillingCycle::create([
            'start_date' => $startDate->toDateString(),
            'end_date' => null,
            'is_open' => true,
            'created_at' => now(),
        ]);

        $this->createWeeksForCycle($cycle);
        return $cycle;
    }

    private function createWeeksForCycle(BillingCycle $cycle): void
    {
        $start = Carbon::parse($cycle->start_date);
        $weeks = [
            [1, $start->copy(), $start->copy()->addDays(6)],
            [2, $start->copy()->addDays(7), $start->copy()->addDays(13)],
            [3, $start->copy()->addDays(14), $start->copy()->addDays(20)],
            [4, $start->copy()->addDays(21), $start->copy()->addDays(27)],
            // Internal placeholder only; it is replaced with the actual final date
            // when the next salary arrives.
            [5, $start->copy()->addDays(28), $start->copy()->addDays(41)],
        ];

        foreach ($weeks as [$number, $weekStart, $weekEnd]) {
            BillingWeek::create([
                'cycle_id' => $cycle->id,
                'week_number' => $number,
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'created_at' => now(),
            ]);
        }
    }
}
