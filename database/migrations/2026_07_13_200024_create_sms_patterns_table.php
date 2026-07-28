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
        Schema::create('sms_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('pattern_hash', 32)->unique();
            $table->string('type');
            $table->string('merchant')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('raw_example');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_patterns');
    }
};
