<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientContext extends Model
{
    use HasFactory;

    protected $table = 'client_contexts';

    protected $fillable = [
        'phone',
        'interested_products',
        'last_interaction',
    ];

    protected function casts(): array
    {
        return [
            'interested_products' => 'array',
            'last_interaction' => 'datetime',
        ];
    }
}
