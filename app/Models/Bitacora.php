<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    public const LOGIN_OK = 'login_ok';
    public const LOGIN_FALLIDO = 'login_fallido';
    public const LOGOUT = 'logout';
    public const ACCESO = 'acceso';
    public const DENEGADO = 'denegado';
    public const ERROR = 'error';

    protected $table = 'bitacora';
    protected $primaryKey = 'id_bitacora';
    public $timestamps = false;
    protected $fillable = ['id_usuario', 'email', 'evento', 'metodo', 'recurso', 'ruta', 'ip',
        'user_agent', 'codigo_http', 'duracion_ms', 'detalle'];
    protected $casts = ['fecha' => 'datetime'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
