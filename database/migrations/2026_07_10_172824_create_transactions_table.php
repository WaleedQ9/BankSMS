<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('billing_cycles')->cascadeOnDelete();
            $table->foreignId('week_id')->constrained('billing_weeks')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('type', ['purchase', 'income', 'transfer', 'atm']);
            $table->decimal('amount', 10, 2);
            $table->string('merchant')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('payment_method')->nullable();
            $table->datetime('transaction_date');
            $table->text('sms_raw');
            $table->boolean('is_classified')->default(false);
            $table->datetime('classified_at')->nullable();
            $table->string('telegram_message_id')->nullable();
            $table->boolean('needs_reminder')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
