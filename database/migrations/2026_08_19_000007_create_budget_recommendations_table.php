<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('billing_cycles')->cascadeOnDelete();
            $table->json('source_cycle_ids');
            $table->json('recommendations');
            $table->text('summary')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique('cycle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_recommendations');
    }
};
