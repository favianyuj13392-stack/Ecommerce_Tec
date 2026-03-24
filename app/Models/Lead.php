<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Lead extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_ai_enabled' => true,
    ];

    protected $fillable = [
        'whatsapp_id',
        'name',
        'interaction_count',
        'interests',
        'is_ai_enabled',
    ];

    protected $casts = [
        'interests' => AsArrayObject::class,
        'interaction_count' => 'integer',
        'is_ai_enabled' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }
}
