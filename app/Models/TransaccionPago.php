<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaccionPago extends Model
{
    protected $table = 'transaccion_pago';
    protected $primaryKey = 'id_transaccion';
    public $timestamps = false;
    protected $fillable = [
        'id_usuario', 'id_metodo', 'id_proforma', 'id_cuota',
        'monto', 'estado', 'codigo_autorizacion', 'mensaje', 'stripe_payment_intent', 'fecha',
    ];
    protected $casts = [
        'monto' => 'float',
        'fecha' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function metodo(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo', 'id_metodo');
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class, 'id_proforma', 'id_proforma');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'id_cuota', 'id_cuota');
    }
}

