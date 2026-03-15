<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Client extends Model
{
    protected $fillable = [
        'phone',
        'abandonment_history',
        'discount_history',
        'purchase_frequency',
    ];

    protected $casts = [
        'abandonment_history' => AsArrayObject::class,
        'discount_history' => AsArrayObject::class,
        'purchase_frequency' => 'integer',
    ];
}
