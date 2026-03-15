<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Chat extends Model
{
    protected $fillable = [
        'phone',
        'history_json',
        'timestamp',
    ];

    protected $casts = [
        'history_json' => AsArrayObject::class,
        'timestamp' => 'datetime',
    ];
}
