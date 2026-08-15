<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleOverageSettlement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cycle_id', 'source_category_id', 'total_deficit', 'covered_amount',
        'uncovered_amount', 'details', 'created_at',
    ];

    protected $casts = [
        'total_deficit' => 'decimal:2',
        'covered_amount' => 'decimal:2',
        'uncovered_amount' => 'decimal:2',
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function sourceCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'source_category_id');
    }
}
