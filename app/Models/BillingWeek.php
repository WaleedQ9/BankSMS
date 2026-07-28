<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingWeek extends Model
{
    public $timestamps = false;

    protected $fillable = ['cycle_id', 'week_number', 'start_date', 'end_date', 'created_at'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class, 'cycle_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'week_id');
    }

    public function budgetAlerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class, 'week_id');
    }
}
