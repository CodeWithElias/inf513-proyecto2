<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPago extends Model
{
    public const FRECUENCIAS = ['mensual', 'quincenal', 'semanal'];
    public const ESTADOS = ['activo', 'completado', 'cancelado'];

    protected $table = 'plan_pago';
    protected $primaryKey = 'id_plan';
    public $timestamps = false;
    protected $fillable = [
        'id_usuario', 'id_proforma', 'monto_total', 'nro_cuotas',
        'frecuencia', 'fecha_inicio', 'estado',
    ];
    protected $casts = [
        'monto_total' => 'float',
        'nro_cuotas' => 'integer',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_registro' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class, 'id_proforma', 'id_proforma');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class, 'id_plan', 'id_plan')->orderBy('nro');
    }
}

