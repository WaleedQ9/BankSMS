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
            $table->date('end_date')->nullable()->change();
        });

        // Open cycles do not have an end date until the following salary is received.
        BillingCycle::where('is_open', true)->update(['end_date' => null]);
    }

    public function down(): void
    {
        BillingCycle::whereNull('end_date')->update(['end_date' => now()->toDateString()]);

        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
