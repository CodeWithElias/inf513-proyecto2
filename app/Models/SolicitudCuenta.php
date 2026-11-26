<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Solicitud de cuenta de un visitante (pendiente → aprobada | rechazada). */
class SolicitudCuenta extends Model
{
    public const PENDIENTE = 'pendiente';
    public const APROBADA = 'aprobada';
    public const RECHAZADA = 'rechazada';

    protected $table = 'solicitud_cuenta';
    protected $primaryKey = 'id_solicitud';
    public $timestamps = false;
    protected $fillable = ['id_visitante', 'password_hash', 'estado', 'motivo_rechazo', 'id_usuario', 'id_revisor', 'fecha_revision'];
    protected $hidden = ['password_hash'];
    protected $casts = ['fecha_solicitud' => 'datetime', 'fecha_revision' => 'datetime'];

    public function visitante(): BelongsTo
    {
        return $this->belongsTo(Visitante::class, 'id_visitante', 'id_visitante');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_revisor', 'id_usuario');
    }
}
