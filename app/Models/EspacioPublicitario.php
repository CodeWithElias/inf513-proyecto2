<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tabla espacio_publicitario (CU5 - RF10).
 */
class EspacioPublicitario extends Model
{
    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_OCUPADO = 'ocupado';
    public const ESTADO_BLOQUEADO = 'bloqueado';

    public const ESTADOS = [
        self::ESTADO_DISPONIBLE,
        self::ESTADO_OCUPADO,
        self::ESTADO_BLOQUEADO,
    ];

    protected $table = 'espacio_publicitario';
    protected $primaryKey = 'id_espacio';
    public $timestamps = false;

    protected $fillable = [
        'id_canal',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalDifusion::class, 'id_canal', 'id_canal');
    }

    public function pauta(): HasOne
    {
        return $this->hasOne(ProgramacionPauta::class, 'id_espacio', 'id_espacio');
    }

    public function estaDisponible(): bool
    {
        return $this->estado === self::ESTADO_DISPONIBLE;
    }

    public function estaOcupado(): bool
    {
        return $this->estado === self::ESTADO_OCUPADO;
    }
}
