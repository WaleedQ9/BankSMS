<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsTransfer extends Model
{
    public $timestamps = false;

    protected $fillable = ['cycle_id', 'amount', 'created_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }
}
