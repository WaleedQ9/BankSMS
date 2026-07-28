<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Category extends Model
{
    protected $fillable = ['name', 'icon', 'color', 'is_active', 'show_in_weekly', 'rollover_type', 'carried_balance'];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_weekly' => 'boolean',
        'carried_balance' => 'decimal:2',
    ];

    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgetAlerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class);
    }
}
