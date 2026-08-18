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
        return $this->getCurrentCycle();
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
                    ->first()
                    ?? $openCycle->weeks()->orderByDesc('week_number')->first();

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
        $week = $cycle->weeks()
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($week) {
            return $week;
        }

        $lastWeek = $cycle->weeks()->orderByDesc('week_number')->first();

        // A delayed salary must not open a cycle automatically. Keep the final
        // planned week active until the actual salary SMS is received.
        if ($lastWeek && $cycle->is_open && $date->isAfter($lastWeek->end_date)) {
            $lastWeek->update(['end_date' => $date->toDateString()]);
        }

        return $lastWeek;
    }

    public function getCurrentCycle(): BillingCycle
    {
        $cycle = BillingCycle::where('is_open', true)->latest('start_date')->first()
            ?? $this->createOpenCycle(Carbon::now());

        $this->synchronizeOpenCycleWeeks($cycle);

        return $cycle;
    }

    public function getCurrentWeek(): BillingWeek
    {
        return $this->getWeekForDate($this->getCurrentCycle(), Carbon::now());
    }

    public function getRemainingWeeksCount(BillingCycle $cycle, BillingWeek $currentWeek): int
    {
        return $cycle->weeks()->where('week_number', '>=', $currentWeek->week_number)->count();
    }

    /**
     * The 27th is the planned payday. Friday payroll arrives on Thursday,
     * while Saturday payroll arrives on Sunday. This date is for planning only;
     * the salary SMS remains the only event that starts a new cycle.
     */
    public function getExpectedSalaryDate(BillingCycle $cycle): Carbon
    {
        $scheduled = $cycle->start_date->copy()->startOfMonth()->addMonth()->day(27);

        return match ($scheduled->dayOfWeek) {
            Carbon::FRIDAY => $scheduled->subDay(),
            Carbon::SATURDAY => $scheduled->addDay(),
            default => $scheduled,
        };
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
        foreach ($this->plannedWeeks($cycle) as [$number, $weekStart, $weekEnd]) {
            BillingWeek::create([
                'cycle_id' => $cycle->id,
                'week_number' => $number,
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'created_at' => now(),
            ]);
        }
    }

    private function synchronizeOpenCycleWeeks(BillingCycle $cycle): void
    {
        $plannedWeeks = $this->plannedWeeks($cycle);

        foreach ($plannedWeeks as [$number, $weekStart, $weekEnd]) {
            $week = $cycle->weeks()->firstOrNew(['week_number' => $number]);
            $week->fill([
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'created_at' => $week->created_at ?? now(),
            ])->save();

            // Keep the transaction's week aligned when an older open cycle is recalculated.
            $week->transactions()
                ->where('cycle_id', $cycle->id)
                ->whereBetween('transaction_date', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
                ->update(['week_id' => $week->id]);
        }

        // Older open cycles may still contain a fifth placeholder week. Move any
        // of its transactions into the fourth period before removing it.
        $finalWeek = $cycle->weeks()->where('week_number', 4)->first();
        if ($finalWeek) {
            $extraWeeks = $cycle->weeks()->where('week_number', '>', 4)->get();
            foreach ($extraWeeks as $extraWeek) {
                $extraWeek->transactions()->update(['week_id' => $finalWeek->id]);
                $extraWeek->delete();
            }
        }
    }

    /** @return array<int, array{int, Carbon, Carbon}> */
    private function plannedWeeks(BillingCycle $cycle): array
    {
        $weekStart = $cycle->start_date->copy()->startOfDay();
        $plannedEnd = $this->getExpectedSalaryDate($cycle)->subDay()->startOfDay();
        $totalDays = $weekStart->diffInDays($plannedEnd) + 1;
        $baseDays = intdiv($totalDays, 4);
        $extraDays = $totalDays % 4;
        $weeks = [];

        // Always show four spending periods. Extra days are added to the first
        // periods (e.g. 31 days becomes 8 / 8 / 8 / 7).
        for ($number = 1; $number <= 4; $number++) {
            $periodDays = $baseDays + ($number <= $extraDays ? 1 : 0);
            $weekEnd = $weekStart->copy()->addDays($periodDays - 1);
            $weeks[] = [$number, $weekStart->copy(), $weekEnd];
            $weekStart = $weekEnd->copy()->addDay();
        }

        return $weeks;
    }
}
