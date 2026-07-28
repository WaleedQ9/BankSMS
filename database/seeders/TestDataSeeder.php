<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $cycleService = app(BillingCycleService::class);
        $categories = Category::all()->keyBy('name');

        // Set budgets
        $budgets = [
            'القروض والالتزامات' => 3550,
            'مصروف أمي' => 1000,
            'مصروف الزوجة' => 1000,
            'مقاضي المنزل' => 1200,
            'المطاعم والترفيه' => 1200,
            'الوقود والسيارة' => 1000,
            'الصحة' => 500,
            'ورد' => 300,
            'الكهرباء' => 450,
            'الاتصالات' => 500,
            'المناسبات' => 300,
            'السفر' => 500,
            'الادخار' => 2000,
            'الطوارئ' => 1000,
            'مصاريف شخصية' => 1000,
            'صيدلية' => 500,
        ];

        foreach ($budgets as $name => $amount) {
            $cat = $categories->get($name);
            if ($cat) {
                Budget::updateOrCreate(
                    ['category_id' => $cat->id],
                    ['monthly_amount' => $amount]
                );
            }
        }

        // 3 cycles: May 27 - Jun 26, Jun 27 - Jul 26, current (Jun 27 - Jul 26 is current)
        $cycleDates = [
            Carbon::parse('2026-04-27'), // Apr 27 - May 26
            Carbon::parse('2026-05-27'), // May 27 - Jun 26
            Carbon::now(),               // Current: Jun 27 - Jul 26
        ];

        $merchants = [
            'purchase' => [
                'بندة', 'التميمي', 'الدانوب', 'كارفور', 'مطعم البيك', 'ماكدونالدز',
                'جرير', 'اكسترا', 'صيدلية النهدي', 'صيدلية الدواء', 'محطة الراجحي',
                'محطة STC', 'SHEIN', 'Amazon.sa', 'نون', 'هنقرستيشن', 'مرسول',
                'ستاربكس', 'تيم هورتنز', 'باسكن روبنز', 'لولو هايبر',
            ],
            'transfer' => ['أمي', 'ندى', 'محمد', 'عبدالله', 'حساب الادخار'],
            'income' => ['راتب', 'حوالة واردة', 'مكافأة'],
        ];

        $categoryMapping = [
            'بندة' => 'مقاضي المنزل', 'التميمي' => 'مقاضي المنزل', 'الدانوب' => 'مقاضي المنزل',
            'كارفور' => 'مقاضي المنزل', 'لولو هايبر' => 'مقاضي المنزل',
            'مطعم البيك' => 'المطاعم والترفيه', 'ماكدونالدز' => 'المطاعم والترفيه',
            'ستاربكس' => 'المطاعم والترفيه', 'تيم هورتنز' => 'المطاعم والترفيه',
            'باسكن روبنز' => 'المطاعم والترفيه', 'هنقرستيشن' => 'المطاعم والترفيه',
            'جرير' => 'مصاريف شخصية', 'اكسترا' => 'مصاريف شخصية',
            'SHEIN' => 'مصروف الزوجة', 'Amazon.sa' => 'مصاريف شخصية', 'نون' => 'مصاريف شخصية',
            'صيدلية النهدي' => 'صيدلية', 'صيدلية الدواء' => 'صيدلية',
            'محطة الراجحي' => 'الوقود والسيارة', 'محطة STC' => 'الاتصالات',
            'مرسول' => 'المطاعم والترفيه',
            'أمي' => 'مصروف أمي', 'ندى' => 'مصروف الزوجة',
            'محمد' => 'المناسبات', 'عبدالله' => 'المناسبات',
            'حساب الادخار' => 'الادخار',
        ];

        foreach ($cycleDates as $cycleIndex => $date) {
            $cycle = $cycleService->getCycleForDate($date);
            $weeks = $cycle->weeks()->orderBy('week_number')->get();
            $isCurrentCycle = $cycleIndex === 2;

            // Income: salary on 27th
            $salaryDate = $cycle->start_date->copy()->addHours(10);
            $this->createTransaction($cycle, $weeks, $salaryDate, [
                'type' => 'income',
                'amount' => 15500,
                'merchant' => 'راتب',
            ], null);

            // Maybe a second income
            if ($cycleIndex === 1) {
                $bonusDate = $cycle->start_date->copy()->addDays(5)->addHours(14);
                $this->createTransaction($cycle, $weeks, $bonusDate, [
                    'type' => 'income',
                    'amount' => 2000,
                    'merchant' => 'مكافأة',
                ], null);
            }

            // Loan payment on 1st
            $loanDate = $cycle->start_date->copy()->addDays(4)->setHour(8);
            $this->createTransaction($cycle, $weeks, $loanDate, [
                'type' => 'purchase',
                'amount' => 3550,
                'merchant' => 'قسط التمويل',
            ], $categories->get('القروض والالتزامات'));

            // Transfer to mom
            $momDate = $cycle->start_date->copy()->addDays(2)->setHour(20);
            $this->createTransaction($cycle, $weeks, $momDate, [
                'type' => 'transfer',
                'amount' => 1000,
                'merchant' => 'أمي',
            ], $categories->get('مصروف أمي'));

            // Electricity (every other cycle)
            if ($cycleIndex % 2 === 0) {
                $elecDate = $cycle->start_date->copy()->addDays(10)->setHour(9);
                $this->createTransaction($cycle, $weeks, $elecDate, [
                    'type' => 'purchase',
                    'amount' => rand(280, 420),
                    'merchant' => 'شركة الكهرباء',
                ], $categories->get('الكهرباء'));
            }

            // STC
            $stcDate = $cycle->start_date->copy()->addDays(8)->setHour(11);
            $this->createTransaction($cycle, $weeks, $stcDate, [
                'type' => 'purchase',
                'amount' => rand(150, 300),
                'merchant' => 'STC',
            ], $categories->get('الاتصالات'));

            // Savings transfer
            $saveDate = $cycle->start_date->copy()->addDays(1)->setHour(21);
            $this->createTransaction($cycle, $weeks, $saveDate, [
                'type' => 'transfer',
                'amount' => 2000,
                'merchant' => 'حساب الادخار',
            ], $categories->get('الادخار'));

            // Generate random daily transactions
            $maxWeek = $isCurrentCycle ? 3 : 4;
            foreach ($weeks as $week) {
                if ($week->week_number > $maxWeek) break;

                $maxDay = 7;
                if ($isCurrentCycle && $week->week_number === $maxWeek) {
                    $maxDay = min(3, now()->diffInDays($week->start_date) + 1);
                }

                for ($day = 0; $day < $maxDay; $day++) {
                    $txDate = $week->start_date->copy()->addDays($day);
                    if ($txDate->gt(now())) break;

                    // 1-3 purchases per day
                    $txCount = rand(1, 3);
                    for ($t = 0; $t < $txCount; $t++) {
                        $merchant = $merchants['purchase'][array_rand($merchants['purchase'])];
                        $catName = $categoryMapping[$merchant] ?? null;
                        $cat = $catName ? $categories->get($catName) : null;

                        $amounts = [
                            'مقاضي المنزل' => rand(30, 350),
                            'المطاعم والترفيه' => rand(15, 120),
                            'الوقود والسيارة' => rand(50, 200),
                            'مصاريف شخصية' => rand(20, 300),
                            'مصروف الزوجة' => rand(50, 250),
                            'صيدلية' => rand(15, 80),
                            'الاتصالات' => rand(50, 150),
                        ];
                        $amount = $amounts[$catName ?? ''] ?? rand(10, 200);

                        $hour = rand(7, 23);
                        $minute = rand(0, 59);

                        $this->createTransaction($cycle, $weeks, $txDate->copy()->setTime($hour, $minute), [
                            'type' => 'purchase',
                            'amount' => $amount,
                            'merchant' => $merchant,
                            'card_last4' => rand(0, 1) ? '4365' : '0699',
                            'payment_method' => ['مدى, Apple Pay', 'مدى', 'بطاقة ائتمان, Apple Pay'][rand(0, 2)],
                        ], $cat);
                    }
                }
            }

            // Wife transfer mid-cycle
            $wifeDate = $cycle->start_date->copy()->addDays(12)->setHour(19);
            if (!$isCurrentCycle || $wifeDate->lte(now())) {
                $this->createTransaction($cycle, $weeks, $wifeDate, [
                    'type' => 'transfer',
                    'amount' => rand(300, 600),
                    'merchant' => 'ندى',
                ], $categories->get('مصروف الزوجة'));
            }

            // Baby expenses
            $babyDate = $cycle->start_date->copy()->addDays(rand(5, 15))->setHour(16);
            if (!$isCurrentCycle || $babyDate->lte(now())) {
                $this->createTransaction($cycle, $weeks, $babyDate, [
                    'type' => 'purchase',
                    'amount' => rand(50, 200),
                    'merchant' => 'مستلزمات أطفال',
                ], $categories->get('ورد'));
            }

            // Archive previous cycles
            if (!$isCurrentCycle) {
                \Artisan::call('cycle:archive', ['--cycle' => $cycle->id]);
            }
        }

        $this->command->info('Test data created: 3 cycles with transactions, 2 archived.');
    }

    private function createTransaction($cycle, $weeks, Carbon $date, array $data, ?Category $category): void
    {
        $week = $weeks->first(fn($w) => $date->between($w->start_date, $w->end_date->endOfDay()));
        if (!$week) $week = $weeks->last();

        Transaction::create([
            'cycle_id' => $cycle->id,
            'week_id' => $week->id,
            'category_id' => $category?->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'merchant' => $data['merchant'],
            'card_last4' => $data['card_last4'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'transaction_date' => $date,
            'sms_raw' => 'test data',
            'is_classified' => $category !== null,
            'classified_at' => $category ? $date : null,
            'needs_reminder' => $category === null && $data['type'] !== 'income',
        ]);
    }
}
