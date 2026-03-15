<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountRule extends Model
{
    protected $fillable = [
        'type',
        'max_percent',
        'active',
    ];

    protected $casts = [
        'max_percent' => 'integer',
        'active' => 'boolean',
    ];
}
