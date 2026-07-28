<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('billing_cycles')->cascadeOnDelete();
            $table->string('category_name');
            $table->string('category_icon', 20);
            $table->string('category_color', 10);
            $table->decimal('budget_amount', 10, 2)->default(0);
            $table->decimal('spent_amount', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2)->default(0);
            $table->decimal('income_total', 10, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_snapshots');
    }
};
