<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\BillingCycle;
use App\Models\Category;
use App\Models\CycleSnapshot;
use App\Models\Setting;
use App\Models\SavingsTransfer;
use App\Models\Transaction;
use Illuminate\Console\Command;

class ArchiveCycleCommand extends Command
{
    protected $signature = 'cycle:archive {--cycle= : Cycle ID to archive (defaults to current)}';
    protected $description = 'Archive a billing cycle snapshot';

    public function handle(): int
    {
        $cycleId = $this->option('cycle');
        if ($cycleId) {
            $cycle = BillingCycle::findOrFail($cycleId);
        } else {
            // Auto-mode: archive all ended cycles that aren't archived yet
            $unarchived = BillingCycle::where('end_date', '<', now()->toDateString())
                ->whereNotIn('id', CycleSnapshot::select('cycle_id')->distinct())
                ->get();

            if ($unarchived->isEmpty()) {
                $this->info('No cycles to archive.');
                return 0;
            }

            foreach ($unarchived as $c) {
                $this->call('cycle:archive', ['--cycle' => $c->id]);
            }
            return 0;
        }

        $cycle = $cycle ?? null;

        if (!$cycle) {
            $this->error('No cycle found.');
            return 1;
        }

        if (CycleSnapshot::where('cycle_id', $cycle->id)->exists()) {
            $this->warn("Cycle {$cycle->id} ({$cycle->start_date->format('d/m')} - {$cycle->end_date->format('d/m')}) already archived.");
            return 0;
        }

        $budgets = Budget::with('category')->get();
        $totalIncome = Transaction::where('cycle_id', $cycle->id)
            ->where('type', 'income')
            ->sum('amount');
        $totalTxCount = Transaction::where('cycle_id', $cycle->id)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->count();

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            if (!$cat) continue;

            $spent = Transaction::where('cycle_id', $cycle->id)
                ->where('category_id', $cat->id)
                ->where('is_classified', true)
                ->sum('amount');
            $effectiveBudget = $budget->monthly_amount + $cat->carried_balance;

            CycleSnapshot::create([
                'cycle_id' => $cycle->id,
                'category_name' => $cat->name,
                'category_icon' => $cat->icon,
                'category_color' => $cat->color,
                'budget_amount' => $effectiveBudget,
                'spent_amount' => $spent,
                'remaining_amount' => $effectiveBudget - $spent,
                'income_total' => 0,
                'transaction_count' => 0,
            ]);
        }

        // Add a summary row for totals
        CycleSnapshot::create([
            'cycle_id' => $cycle->id,
            'category_name' => '__summary__',
            'category_icon' => '',
            'category_color' => '',
            'budget_amount' => $budgets->sum('monthly_amount'),
            'spent_amount' => Transaction::where('cycle_id', $cycle->id)
                ->whereIn('type', ['purchase', 'transfer', 'atm'])
                ->sum('amount'),
            'remaining_amount' => 0,
            'income_total' => $totalIncome,
            'transaction_count' => $totalTxCount,
        ]);

        // Process budget rollover
        $savingsCategoryId = Setting::getValue('savings_category_id');
        $savingsCategory = $savingsCategoryId ? Category::find($savingsCategoryId) : null;
        $savingsCarry = 0;

        foreach ($budgets as $budget) {
            $cat = $budget->category;
            if (!$cat || !$cat->is_active) continue;

            $spent = Transaction::where('cycle_id', $cycle->id)
                ->where('category_id', $cat->id)
                ->where('is_classified', true)
                ->sum('amount');

            $remaining = ($budget->monthly_amount + $cat->carried_balance) - $spent;
            if ($remaining <= 0) {
                $cat->update(['carried_balance' => 0]);
                continue;
            }

            if ($cat->rollover_type === 'rollover') {
                $cat->update(['carried_balance' => $remaining]);
                $this->info("  {$cat->name}: {$remaining} carried over");
            } else {
                // savings - transfer to savings category
                $savingsCarry += $remaining;
                $cat->update(['carried_balance' => 0]);
                $this->info("  {$cat->name}: {$remaining} → savings");
            }
        }

        if ($savingsCarry > 0 && $savingsCategory) {
            $savingsCategory->increment('carried_balance', $savingsCarry);
            $this->info("  Total added to savings: {$savingsCarry}");
        }

        SavingsTransfer::updateOrCreate(
            ['cycle_id' => $cycle->id],
            ['amount' => $savingsCategory ? $savingsCarry : 0, 'created_at' => now()]
        );

        $this->info("Cycle {$cycle->id} ({$cycle->start_date->format('d/m')} - {$cycle->end_date->format('d/m')}) archived successfully.");
        return 0;
    }
}
