<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleProforma extends Model
{
    protected $table = 'detalle_proforma';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;
    protected $fillable = [
        'cantidad', 'precio_unitario', 'subtotal', 'frecuencia',
        'id_proforma', 'id_tarifa', 'id_paquete',
    ];
    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'float',
        'subtotal' => 'float',
    ];

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class, 'id_proforma', 'id_proforma');
    }

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class, 'id_tarifa', 'id_tarifa');
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(Paquete::class, 'id_paquete', 'id_paquete');
    }
}

