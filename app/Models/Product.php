<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'precio',
        'marca',
        'categoria',
        'attributes',
        'has_variants',
    ];

    protected $casts = [
        'attributes' => AsArrayObject::class,
        'has_variants' => 'boolean',
        'precio' => 'decimal:2',
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
