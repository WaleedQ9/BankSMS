<?php

use App\Models\BillingCycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->boolean('is_open')->default(false)->after('end_date');
        });

        BillingCycle::query()->update(['is_open' => false]);
        BillingCycle::query()->latest('start_date')->first()?->update(['is_open' => true]);
    }

    public function down(): void
    {
        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->dropColumn('is_open');
        });
    }
};
