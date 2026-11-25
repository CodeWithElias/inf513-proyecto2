<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paquete extends Model
{
    protected $table = 'paquete';
    protected $primaryKey = 'id_paquete';
    public $timestamps = false;
    protected $fillable = ['nombre', 'descripcion', 'descuento', 'activo', 'precio_total'];
    protected $casts = [
        'descuento' => 'float',
        'precio_total' => 'float',
        'activo' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PaqueteItem::class, 'id_paquete', 'id_paquete');
    }
}

