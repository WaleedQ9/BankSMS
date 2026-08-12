<?php

use App\Models\BillingCycle;
use App\Models\BillingWeek;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        BillingCycle::where('is_open', true)->each(function (BillingCycle $cycle): void {
            $start = Carbon::parse($cycle->start_date);
            $cycle->update(['end_date' => $start->copy()->addDays(41)->toDateString()]);

            $fourthWeek = $cycle->weeks()->where('week_number', 4)->first();
            if ($fourthWeek) {
                $fourthWeek->update(['end_date' => $start->copy()->addDays(27)->toDateString()]);
            }

            $fifthWeek = BillingWeek::firstOrCreate(
                ['cycle_id' => $cycle->id, 'week_number' => 5],
                [
                    'start_date' => $start->copy()->addDays(28)->toDateString(),
                    'end_date' => $start->copy()->addDays(41)->toDateString(),
                    'created_at' => now(),
                ]
            );

            Transaction::where('cycle_id', $cycle->id)
                ->whereDate('transaction_date', '>=', $fifthWeek->start_date)
                ->update(['week_id' => $fifthWeek->id]);
        });
    }

    public function down(): void
    {
        // Historical financial periods are not rewritten on rollback.
    }
};
