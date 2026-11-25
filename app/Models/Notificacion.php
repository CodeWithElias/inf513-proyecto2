<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla notificacion (CU5 - RF11).
 */
class Notificacion extends Model
{
    public const MEDIO_SISTEMA = 'sistema';
    public const MEDIO_CORREO = 'correo';

    protected $table = 'notificacion';
    protected $primaryKey = 'id_notificacion';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_programacion',
        'id_deteccion',
        'medio',
        'titulo',
        'mensaje',
        'leida',
        'fecha_envio',
    ];

    protected $casts = [
        'leida' => 'boolean',
        'fecha_envio' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function pauta(): BelongsTo
    {
        return $this->belongsTo(ProgramacionPauta::class, 'id_programacion', 'id_programacion');
    }
}
