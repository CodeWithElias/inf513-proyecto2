<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarifa extends Model
{
    protected $table = 'tarifa';
    protected $primaryKey = 'id_tarifa';
    public $timestamps = false;
    protected $fillable = [
        'monto', 'unidad_cobro', 'vigente_desde', 'vigente_hasta',
        'duracion_seg', 'activo', 'id_servicio', 'id_unidad',
    ];
    protected $casts = [
        'monto' => 'float',
        'duracion_seg' => 'integer',
        'activo' => 'boolean',
        'vigente_desde' => 'date:Y-m-d',
        'vigente_hasta' => 'date:Y-m-d',
    ];

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioPublicitario::class, 'id_servicio', 'id_servicio');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadUniversitaria::class, 'id_unidad', 'id_unidad');
    }
}

