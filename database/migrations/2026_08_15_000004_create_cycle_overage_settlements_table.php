<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_overage_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->unique()->constrained('billing_cycles')->cascadeOnDelete();
            $table->foreignId('source_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('total_deficit', 10, 2)->default(0);
            $table->decimal('covered_amount', 10, 2)->default(0);
            $table->decimal('uncovered_amount', 10, 2)->default(0);
            $table->json('details');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_overage_settlements');
    }
};
