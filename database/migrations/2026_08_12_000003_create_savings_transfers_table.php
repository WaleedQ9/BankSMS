<?php

use App\Models\BillingCycle;
use App\Models\Category;
use App\Models\CycleSnapshot;
use App\Models\SavingsTransfer;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->unique()->constrained('billing_cycles')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        // The existing July archive predates this table. Its accumulated savings balance
        // is recorded once against the latest archived cycle so it also appears in history.
        $savingsCategory = Category::find(Setting::getValue('savings_category_id'));
        $latestArchivedCycle = BillingCycle::whereHas('transactions')
            ->whereIn('id', CycleSnapshot::select('cycle_id')->distinct())
            ->latest('start_date')
            ->first();

        if ($savingsCategory && $latestArchivedCycle && $savingsCategory->carried_balance > 0) {
            SavingsTransfer::firstOrCreate(
                ['cycle_id' => $latestArchivedCycle->id],
                ['amount' => $savingsCategory->carried_balance, 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transfers');
    }
};
