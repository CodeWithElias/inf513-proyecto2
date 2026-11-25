<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Proforma extends Model
{
    public const ESTADOS = ['pendiente', 'aprobada', 'rechazada', 'vencida'];

    protected $table = 'proforma';
    protected $primaryKey = 'id_proforma';
    public $timestamps = false;
    protected $fillable = [
        'nro_proforma', 'fecha_emision', 'fecha_validez',
        'descuento', 'total', 'estado', 'subtotal',
        'id_empresa', 'id_visitante',
    ];
    protected $casts = [
        'descuento' => 'float',
        'subtotal' => 'float',
        'total' => 'float',
        'fecha_emision' => 'date:Y-m-d',
        'fecha_validez' => 'date:Y-m-d',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaAnunciante::class, 'id_empresa', 'id_empresa');
    }

    public function visitante(): BelongsTo
    {
        return $this->belongsTo(Visitante::class, 'id_visitante', 'id_visitante');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleProforma::class, 'id_proforma', 'id_proforma');
    }

    public function campania(): HasOne
    {
        return $this->hasOne(Campania::class, 'id_proforma', 'id_proforma');
    }

    public function planesPago(): HasMany
    {
        return $this->hasMany(PlanPago::class, 'id_proforma', 'id_proforma');
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(TransaccionPago::class, 'id_proforma', 'id_proforma');
    }
}

