<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\BillingWeek;
use Carbon\Carbon;

class BillingCycleService
{
    public function getCycleForDate(Carbon $date): BillingCycle
    {
        // أولاً: ابحث عن دورة موجودة يقع فيها التاريخ
        $cycle = BillingCycle::where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($cycle) {
            return $cycle;
        }

        // ثانياً: أنشئ دورة جديدة
        if ($date->day >= 27) {
            $startDate = Carbon::create($date->year, $date->month, 27);
            $endDate   = Carbon::create($date->year, $date->month, 27)->addMonth()->setDay(26);
        } else {
            $startDate = Carbon::create($date->year, $date->month, 27)->subMonth();
            $endDate   = Carbon::create($date->year, $date->month, 26);
        }

        $cycle = BillingCycle::create([
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'created_at' => now(),
        ]);

        $this->createWeeksForCycle($cycle);

        return $cycle;
    }

    public function getWeekForDate(BillingCycle $cycle, Carbon $date): BillingWeek
    {
        // ابحث عن الأسبوع المناسب
        $week = $cycle->weeks()
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        // لو ما لقى أسبوع (نادر) - أرجع آخر أسبوع في الدورة
        if (!$week) {
            $week = $cycle->weeks()->orderByDesc('week_number')->first();
        }

        return $week;
    }

    private function createWeeksForCycle(BillingCycle $cycle): void
    {
        $start = Carbon::parse($cycle->start_date);

        $weeks = [
            ['week_number' => 1, 'start' => $start->copy(),            'end' => $start->copy()->addDays(6)],
            ['week_number' => 2, 'start' => $start->copy()->addDays(7),  'end' => $start->copy()->addDays(13)],
            ['week_number' => 3, 'start' => $start->copy()->addDays(14), 'end' => $start->copy()->addDays(20)],
            ['week_number' => 4, 'start' => $start->copy()->addDays(21), 'end' => Carbon::parse($cycle->end_date)],
        ];

        foreach ($weeks as $week) {
            BillingWeek::create([
                'cycle_id'    => $cycle->id,
                'week_number' => $week['week_number'],
                'start_date'  => $week['start']->toDateString(),
                'end_date'    => $week['end']->toDateString(),
                'created_at'  => now(),
            ]);
        }
    }

    public function getCurrentCycle(): BillingCycle
    {
        return $this->getCycleForDate(Carbon::now());
    }

    public function getCurrentWeek(): BillingWeek
    {
        $cycle = $this->getCurrentCycle();
        return $this->getWeekForDate($cycle, Carbon::now());
    }

    public function getRemainingWeeksCount(BillingCycle $cycle, BillingWeek $currentWeek): int
    {
        return $cycle->weeks()
            ->where('week_number', '>=', $currentWeek->week_number)
            ->count();
    }
}