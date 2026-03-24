<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'lead_id',
        'message_id',
        'body',
        'direction',
        'source',
        'tokens_used',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
