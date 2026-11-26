<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetodoPago extends Model
{
    /** Solo tarjetas (por Stripe). Los registros «qr» y «billetera» anteriores quedan como historial. */
    public const TIPOS = ['tarjeta'];

    protected $table = 'metodo_pago';
    protected $primaryKey = 'id_metodo';
    public $timestamps = false;
    protected $fillable = [
        'id_usuario', 'tipo', 'marca', 'ultimos4', 'titular',
        'mes_exp', 'anio_exp', 'token_simulado', 'stripe_payment_method', 'predeterminado', 'activo',
    ];
    protected $hidden = ['token_simulado', 'stripe_payment_method'];
    protected $appends = ['con_stripe'];

    /** ¿Se puede cobrar con Stripe? (las registradas antes de Stripe no) */
    public function getConStripeAttribute(): bool
    {
        return (bool) $this->stripe_payment_method;
    }
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

