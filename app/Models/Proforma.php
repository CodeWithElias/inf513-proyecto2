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

    /** Situaciones de pago: con «pagada» o «al_dia» ya se pueden programar pautas. */
    public const PAGO_PAGADA = 'pagada';      // pago al contado aprobado o plan completado

    public const PAGO_AL_DIA = 'al_dia';      // plan activo con cuotas pagadas y ninguna vencida

    public const PAGO_ATRASADO = 'atrasado';  // plan con alguna cuota vencida sin pagar

    public const PAGO_PENDIENTE = 'sin_pago'; // aún no pagó nada

    /**
     * Situación de pago de la proforma (paso previo a programar pautas).
     *
     * @return array{estado: string, detalle: string, habilita_pautas: bool}
     */
    public function situacionPago(): array
    {
        $contado = $this->transacciones()->whereNull('id_cuota')->where('estado', 'aprobada')->exists();
        $plan = $this->planesPago()->whereIn('estado', ['activo', 'completado'])->with('cuotas')->latest('id_plan')->first();

        if ($contado || $plan?->estado === 'completado') {
            return ['estado' => self::PAGO_PAGADA, 'detalle' => $contado ? 'Pagada al contado.' : 'Plan de cuotas completado.', 'habilita_pautas' => true];
        }
        if ($plan) {
            $hoy = now()->toDateString();
            $pagadas = $plan->cuotas->where('estado', 'pagada')->count();
            $vencidas = $plan->cuotas->filter(fn ($c) => $c->estado !== 'pagada' && $c->fecha_vencimiento?->toDateString() < $hoy)->count();
            $total = $plan->cuotas->count();
            if ($vencidas > 0) {
                return ['estado' => self::PAGO_ATRASADO, 'detalle' => "Plan de cuotas con {$vencidas} cuota(s) vencida(s) sin pagar ({$pagadas} de {$total} pagadas).", 'habilita_pautas' => false];
            }
            if ($pagadas > 0) {
                return ['estado' => self::PAGO_AL_DIA, 'detalle' => "Plan de cuotas al día ({$pagadas} de {$total} pagadas).", 'habilita_pautas' => true];
            }

            return ['estado' => self::PAGO_PENDIENTE, 'detalle' => "Plan de {$total} cuotas creado; falta pagar la primera.", 'habilita_pautas' => false];
        }

        return ['estado' => self::PAGO_PENDIENTE, 'detalle' => 'Sin pagos: pague al contado o cree un plan de cuotas.', 'habilita_pautas' => false];
    }
}

