<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp_id',
        'name',
        'interaction_count',
        'interests',
    ];

    protected $casts = [
        'interests' => AsArrayObject::class,
        'interaction_count' => 'integer',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
