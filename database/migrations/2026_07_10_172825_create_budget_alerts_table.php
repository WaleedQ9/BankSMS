<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('week_id')->constrained('billing_weeks')->cascadeOnDelete();
            $table->enum('alert_type', ['warning_80', 'exceeded_100']);
            $table->datetime('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_alerts');
    }
};
