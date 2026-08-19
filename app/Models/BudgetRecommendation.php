<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetRecommendation extends Model
{
    protected $fillable = ['cycle_id', 'source_cycle_ids', 'recommendations', 'summary', 'applied_at'];

    protected $casts = [
        'source_cycle_ids' => 'array',
        'recommendations' => 'array',
        'applied_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }
}
