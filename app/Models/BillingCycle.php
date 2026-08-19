<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    public $timestamps = false;

    protected $fillable = ['start_date', 'end_date', 'is_open', 'created_at'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'is_open' => 'boolean',
    ];

    public function weeks(): HasMany
    {
        return $this->hasMany(BillingWeek::class, 'cycle_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'cycle_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CycleSnapshot::class, 'cycle_id');
    }
}
