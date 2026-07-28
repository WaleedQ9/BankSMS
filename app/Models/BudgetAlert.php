<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAlert extends Model
{
    public $timestamps = false;

    protected $fillable = ['category_id', 'week_id', 'alert_type', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(BillingWeek::class, 'week_id');
    }
}
