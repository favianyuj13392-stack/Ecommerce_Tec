<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;

class Qr extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'code',
        'external_qr_id',
        'qr',
        'amount',
        'status',
        'donor_name',
        'voucher_id',
        'payment_date',
        'bnb_blob',
        'expiration_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'expiration_date' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
