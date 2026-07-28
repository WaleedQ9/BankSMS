<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cycle_id', 'category_name', 'category_icon', 'category_color',
        'budget_amount', 'spent_amount', 'remaining_amount',
        'income_total', 'transaction_count', 'created_at',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'income_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class, 'cycle_id');
    }
}
