<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla programacion_pauta (CU5 - RF10/RF11).
 */
class ProgramacionPauta extends Model
{
    public const ESTADO_PROGRAMADA = 'programada';
    /** Mismo valor que NPauta.PUBLICADA del Proyecto 1 (la BD es compartida). */
    public const ESTADO_EMITIDA = 'publicada';
    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADOS = [
        self::ESTADO_PROGRAMADA,
        self::ESTADO_EMITIDA,
        self::ESTADO_CANCELADA,
    ];

    protected $table = 'programacion_pauta';
    protected $primaryKey = 'id_programacion';
    public $timestamps = false;

    protected $fillable = [
        'id_espacio',
        'id_material',
        'id_detalle',
        'id_usuario',
        'estado',
        'url_publicacion',
        'fecha_programacion',
    ];

    protected $casts = [
        'fecha_programacion' => 'datetime',
    ];

    public function espacio(): BelongsTo
    {
        return $this->belongsTo(EspacioPublicitario::class, 'id_espacio', 'id_espacio');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialPublicitario::class, 'id_material', 'id_material');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(DetalleProforma::class, 'id_detalle', 'id_detalle');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'id_programacion', 'id_programacion');
    }

    public function estaProgramada(): bool
    {
        return $this->estado === self::ESTADO_PROGRAMADA;
    }
}
