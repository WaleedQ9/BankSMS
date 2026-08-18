<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingSalaryConfirmation extends Model
{
    protected $fillable = [
        'sms_hash', 'amount', 'merchant', 'card_last4', 'payment_method',
        'transaction_date', 'sms_raw', 'status', 'resolved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
