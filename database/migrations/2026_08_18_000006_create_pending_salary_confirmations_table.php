<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_salary_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('sms_hash', 64)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('merchant')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('payment_method')->nullable();
            $table->dateTime('transaction_date');
            $table->text('sms_raw');
            $table->enum('status', ['pending', 'started_cycle', 'recorded_income', 'ignored'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_salary_confirmations');
    }
};
