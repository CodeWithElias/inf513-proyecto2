<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuota extends Model
{
    public const ESTADOS = ['pendiente', 'pagada', 'vencida'];

    protected $table = 'cuota';
    protected $primaryKey = 'id_cuota';
    public $timestamps = false;
    protected $fillable = ['id_plan', 'nro', 'monto', 'fecha_vencimiento', 'estado', 'fecha_pago'];
    protected $casts = [
        'nro' => 'integer',
        'monto' => 'float',
        'fecha_vencimiento' => 'date:Y-m-d',
        'fecha_pago' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanPago::class, 'id_plan', 'id_plan');
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(TransaccionPago::class, 'id_cuota', 'id_cuota');
    }
}

