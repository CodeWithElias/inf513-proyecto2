<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetodoPago extends Model
{
    public const TIPOS = ['tarjeta', 'qr', 'billetera'];

    protected $table = 'metodo_pago';
    protected $primaryKey = 'id_metodo';
    public $timestamps = false;
    protected $fillable = [
        'id_usuario', 'tipo', 'marca', 'ultimos4', 'titular',
        'mes_exp', 'anio_exp', 'token_simulado', 'predeterminado', 'activo',
    ];
    protected $hidden = ['token_simulado'];
    protected $casts = [
        'mes_exp' => 'integer',
        'anio_exp' => 'integer',
        'predeterminado' => 'boolean',
        'activo' => 'boolean',
        'fecha_registro' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}

