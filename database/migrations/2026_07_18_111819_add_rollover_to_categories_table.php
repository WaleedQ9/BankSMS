<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('rollover_type', ['rollover', 'savings'])->default('rollover')->after('show_in_weekly');
            $table->decimal('carried_balance', 10, 2)->default(0)->after('rollover_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['rollover_type', 'carried_balance']);
        });
    }
};
