<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsPattern extends Model
{
    protected $fillable = [
        'pattern_hash',
        'type',
        'merchant',
        'payment_method',
        'raw_example',
    ];
}
