<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'cycle_id', 'week_id', 'category_id', 'type', 'amount',
        'merchant', 'card_last4', 'payment_method', 'transaction_date',
        'sms_raw', 'is_classified', 'classified_at', 'telegram_message_id',
        'needs_reminder',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'is_classified' => 'boolean',
        'classified_at' => 'datetime',
        'needs_reminder' => 'boolean',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class, 'cycle_id');
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(BillingWeek::class, 'week_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
