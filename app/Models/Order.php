<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model
{
    use HasFactory, HasUuids;

    public function uniqueIds()
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'items',
        'total',
        'user_id',
        'lead_id',
        'guest_data',
        'payment_method',
        'type',
        'status',
        'session_uuid',
        'total_amount',
    ];

    protected $casts = [
        'guest_data' => AsArrayObject::class,
        'total_amount' => 'decimal:2',
        'items' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function qrs()
    {
        return $this->hasMany(Qr::class);
    }
}
