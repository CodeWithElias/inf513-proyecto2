<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Cuota;
use App\Models\MetodoPago;
use App\Models\PlanPago;
use App\Models\Proforma;
use App\Models\TransaccionPago;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Pagos - Pasarela simulada, pago único de proformas, planes de cuotas,
 * pago de cuotas e historial de transacciones.
 */
class PagoService
{
    public function __construct(
        private readonly ProformaService $proformas,
        private readonly MetodoPagoService $metodos,
        private readonly AvisoService $avisos,
        private readonly StripeService $stripe,
    ) {
    }

    public function listarTransacciones(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        return TransaccionPago::query()
            ->with([
                'usuario:id_usuario,nombre_completo,email',
                'metodo:id_metodo,tipo,marca,ultimos4,titular',
                'proforma:id_proforma,nro_proforma,total,estado',
                'cuota:id_cuota,id_plan,nro,monto,fecha_vencimiento,estado',
            ])
            ->when(! $actor->esPersonal(), function ($q) use ($actor) {
                $q->where(function ($w) use ($actor) {
                    $w->where('id_usuario', $actor->id_usuario);
                    $w->orWhereHas('proforma', fn ($p) => $actor->filtrarProformas($p));
                });
            })
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('codigo_autorizacion', 'ilike', "%{$v}%")
                ->orWhere('mensaje', 'ilike', "%{$v}%")
                ->orWhereHas('proforma', fn ($p) => $p->where('nro_proforma', 'ilike', "%{$v}%"))))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['id_proforma'] ?? null, fn ($q, $v) => $q->where('id_proforma', $v))
            ->orderByDesc('fecha')
            ->orderByDesc('id_transaccion')
            ->paginate($porPagina);
    }

    /** Pago único del monto total de una proforma aprobada. */
    public function pagarProforma(array $d, Usuario $actor): TransaccionPago
    {
        $proforma = Proforma::find($d['id_proforma']);
        if (! $proforma) {
            throw new NegocioException('La proforma seleccionada no existe.');
        }
        $this->proformas->exigirAcceso($proforma, $actor);

        if ($proforma->estado !== 'aprobada') {
            throw new NegocioException("Solo se pueden pagar proformas aprobadas (estado actual: {$proforma->estado}).");
        }
        if ($proforma->total <= 0) {
            throw new NegocioException('La proforma no tiene un monto válido para pagar.');
        }
        if (TransaccionPago::where('id_proforma', $proforma->id_proforma)->whereNull('id_cuota')->where('estado', 'aprobada')->exists()) {
            throw new NegocioException("La proforma {$proforma->nro_proforma} ya fue pagada anteriormente.");
        }
        if (PlanPago::where('id_proforma', $proforma->id_proforma)->whereIn('estado', ['activo', 'completado'])->exists()) {
            throw new NegocioException("La proforma {$proforma->nro_proforma} ya cuenta con un plan de pagos.");
        }

        $metodo = $this->obtenerMetodoValido((int) $d['id_metodo'], $actor);
        $resultado = $this->stripe->cobrar($metodo, (float) $proforma->total, "Proforma {$proforma->nro_proforma} (al contado)",
            ['concepto' => "contado:{$proforma->id_proforma}", 'id_proforma' => $proforma->id_proforma], $d['payment_intent'] ?? null);

        $tx = TransaccionPago::create([
            'id_usuario' => $actor->id_usuario,
            'id_metodo' => $metodo->id_metodo,
            'id_proforma' => $proforma->id_proforma,
            'id_cuota' => null,
            'monto' => $proforma->total,
            'estado' => $resultado['estado'],
            'codigo_autorizacion' => $resultado['codigo'],
            'mensaje' => $resultado['mensaje'],
            'stripe_payment_intent' => $resultado['payment_intent'],
            'fecha' => now(),
        ]);
        if ($tx->estado === 'aprobada') {
            $this->avisarPago($proforma, 'al contado', (float) $tx->monto, $actor);
        }

        return $tx->load([
            'metodo:id_metodo,tipo,marca,ultimos4,titular',
            'proforma:id_proforma,nro_proforma,total,estado',
        ]);
    }

    public function listarPlanes(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        $this->marcarCuotasVencidas();

        return PlanPago::query()
            ->with([
                'usuario:id_usuario,nombre_completo,email',
                'proforma:id_proforma,nro_proforma,total,estado,id_empresa',
                'proforma.empresa:id_empresa,razon_social',
                'cuotas',
            ])
            ->when(! $actor->esPersonal(), function ($q) use ($actor) {
                $q->where(function ($w) use ($actor) {
                    $w->where('id_usuario', $actor->id_usuario);
                    $w->orWhereHas('proforma', fn ($p) => $actor->filtrarProformas($p));
                });
            })
            ->when($f['q'] ?? null, fn ($q, $v) => $q->whereHas('proforma', fn ($p) => $p->where('nro_proforma', 'ilike', "%{$v}%")))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->orderByDesc('id_plan')
            ->paginate($porPagina);
    }

    public function obtenerPlan(PlanPago $plan, Usuario $actor): PlanPago
    {
        $this->marcarCuotasVencidas();
        $plan->load([
            'usuario:id_usuario,nombre_completo,email',
            'proforma:id_proforma,nro_proforma,total,estado,id_empresa',
            'proforma.empresa:id_empresa,razon_social',
            'cuotas.transacciones.metodo:id_metodo,tipo,marca,ultimos4',
        ]);
        $this->exigirAccesoPlan($plan, $actor);

        return $plan;
    }

    public function crearPlan(array $d, Usuario $actor): PlanPago
    {
        $proforma = Proforma::find($d['id_proforma']);
        if (! $proforma) {
            throw new NegocioException('La proforma seleccionada no existe.');
        }
        $this->proformas->exigirAcceso($proforma, $actor);

        if ($proforma->estado !== 'aprobada') {
            throw new NegocioException("Solo se puede crear un plan de pagos para una proforma aprobada (estado actual: {$proforma->estado}).");
        }
        if ($proforma->total <= 0) {
            throw new NegocioException('La proforma no tiene un monto válido para financiar.');
        }
        if (TransaccionPago::where('id_proforma', $proforma->id_proforma)->whereNull('id_cuota')->where('estado', 'aprobada')->exists()) {
            throw new NegocioException("La proforma {$proforma->nro_proforma} ya fue pagada al contado.");
        }
        if (PlanPago::where('id_proforma', $proforma->id_proforma)->whereIn('estado', ['activo', 'completado'])->exists()) {
            throw new NegocioException("La proforma {$proforma->nro_proforma} ya tiene un plan de pagos activo o completado.");
        }

        $nroCuotas = (int) $d['nro_cuotas'];
        $frecuencia = $d['frecuencia'] ?? 'mensual';
        $fechaInicio = Carbon::parse($d['fecha_inicio'] ?? now()->toDateString());
        $total = round((float) $proforma->total, 2);

        $montoBase = floor(($total / $nroCuotas) * 100) / 100;
        $ultimaCuota = round($total - ($montoBase * ($nroCuotas - 1)), 2);

        return DB::transaction(function () use ($actor, $proforma, $total, $nroCuotas, $frecuencia, $fechaInicio, $montoBase, $ultimaCuota) {
            $plan = PlanPago::create([
                'id_usuario' => $actor->id_usuario,
                'id_proforma' => $proforma->id_proforma,
                'monto_total' => $total,
                'nro_cuotas' => $nroCuotas,
                'frecuencia' => $frecuencia,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'estado' => 'activo',
            ]);

            for ($i = 1; $i <= $nroCuotas; $i++) {
                $vencimiento = match ($frecuencia) {
                    'semanal' => $fechaInicio->copy()->addWeeks($i - 1),
                    'quincenal' => $fechaInicio->copy()->addDays(($i - 1) * 15),
                    default => $fechaInicio->copy()->addMonths($i - 1),
                };

                Cuota::create([
                    'id_plan' => $plan->id_plan,
                    'nro' => $i,
                    'monto' => $i === $nroCuotas ? $ultimaCuota : $montoBase,
                    'fecha_vencimiento' => $vencimiento->toDateString(),
                    'estado' => 'pendiente',
                ]);
            }

            return $plan->load(['proforma:id_proforma,nro_proforma,total,estado', 'cuotas']);
        });
    }

    /** Pagar una cuota específica (al aprobarse, actualiza cuota y dispara trg_cuota_pagada). */
    public function pagarCuota(Cuota $cuota, array $d, Usuario $actor): TransaccionPago
    {
        $cuota->load('plan.proforma');
        $plan = $cuota->plan;
        $this->exigirAccesoPlan($plan, $actor);

        if ($plan->estado !== 'activo') {
            throw new NegocioException("El plan de pagos no está activo (estado actual: {$plan->estado}).");
        }
        if ($cuota->estado === 'pagada') {
            throw new NegocioException("La cuota #{$cuota->nro} ya se encuentra pagada.");
        }
        // Las cuotas se pagan en orden: así el plan nunca queda con huecos
        $anterior = Cuota::where('id_plan', $plan->id_plan)->where('nro', '<', $cuota->nro)
            ->where('estado', '<>', 'pagada')->orderBy('nro')->value('nro');
        if ($anterior !== null) {
            throw new NegocioException("Pague primero la cuota #{$anterior}: las cuotas se pagan en orden.");
        }

        $metodo = $this->obtenerMetodoValido((int) $d['id_metodo'], $actor);
        $resultado = $this->stripe->cobrar($metodo, (float) $cuota->monto, "Proforma {$plan->proforma->nro_proforma} · cuota {$cuota->nro} de {$plan->nro_cuotas}",
            ['concepto' => "cuota:{$cuota->id_cuota}", 'id_proforma' => $plan->id_proforma, 'id_cuota' => $cuota->id_cuota], $d['payment_intent'] ?? null);

        return DB::transaction(function () use ($actor, $metodo, $plan, $cuota, $resultado) {
            $tx = TransaccionPago::create([
                'id_usuario' => $actor->id_usuario,
                'id_metodo' => $metodo->id_metodo,
                'id_proforma' => $plan->id_proforma,
                'id_cuota' => $cuota->id_cuota,
                'monto' => $cuota->monto,
                'estado' => $resultado['estado'],
                'codigo_autorizacion' => $resultado['codigo'],
                'mensaje' => $resultado['mensaje'],
                'stripe_payment_intent' => $resultado['payment_intent'],
                'fecha' => now(),
            ]);

            if ($resultado['estado'] === 'aprobada') {
                $cuota->update([
                    'estado' => 'pagada',
                    'fecha_pago' => now(),
                ]);
                $this->avisarPago($plan->proforma, "cuota {$cuota->nro} de {$plan->nro_cuotas}", (float) $cuota->monto, $actor);
            }

            return $tx->load([
                'metodo:id_metodo,tipo,marca,ultimos4,titular',
                'proforma:id_proforma,nro_proforma,total,estado',
                'cuota.plan',
            ]);
        });
    }

    // ------------------------------------------------------------------ contratación guiada

    /**
     * Cobra con Stripe (o verifica el cobro tras 3D Secure si llega $paymentIntent). No escribe en la BD.
     *
     * @return array{0: MetodoPago, 1: array}
     */
    public function cobrar(int $idMetodo, float $monto, string $descripcion, array $meta, ?string $paymentIntent, Usuario $actor): array
    {
        $metodo = $this->obtenerMetodoValido($idMetodo, $actor);

        return [$metodo, $this->stripe->cobrar($metodo, $monto, $descripcion, $meta, $paymentIntent)];
    }

    /** Devuelve un cobro aprobado cuando la operación no pudo completarse después de cobrar. */
    public function reembolsar(?string $paymentIntent): void
    {
        $this->stripe->reembolsar($paymentIntent);
    }

    public function montoPrimeraCuota(float $total, int $nroCuotas): float
    {
        if ($nroCuotas < 2 || $nroCuotas > 24) {
            throw new NegocioException('El plan de cuotas debe tener entre 2 y 24 cuotas.');
        }

        return floor(($total / $nroCuotas) * 100) / 100;
    }

    /** Intento rechazado por la pasarela: queda registrado aunque la proforma siga pendiente. */
    public function registrarRechazo(Proforma $proforma, MetodoPago $metodo, float $monto, array $resultado, Usuario $actor): TransaccionPago
    {
        return TransaccionPago::create([
            'id_usuario' => $actor->id_usuario, 'id_metodo' => $metodo->id_metodo, 'id_proforma' => $proforma->id_proforma,
            'id_cuota' => null, 'monto' => $monto, 'estado' => $resultado['estado'], 'codigo_autorizacion' => $resultado['codigo'],
            'mensaje' => $resultado['mensaje'], 'stripe_payment_intent' => $resultado['payment_intent'] ?? null, 'fecha' => now(),
        ]);
    }

    /**
     * Cobro aprobado de la contratación (la proforma ya está aprobada dentro de la misma transacción):
     * contado → una transacción por el total; cuotas → plan + primera cuota pagada.
     */
    public function registrarCobroContratacion(Proforma $proforma, MetodoPago $metodo, array $resultado, string $modalidad, array $d, Usuario $actor): TransaccionPago
    {
        if ($modalidad !== 'cuotas') {
            return TransaccionPago::create([
                'id_usuario' => $actor->id_usuario, 'id_metodo' => $metodo->id_metodo, 'id_proforma' => $proforma->id_proforma,
                'id_cuota' => null, 'monto' => $proforma->total, 'estado' => 'aprobada', 'codigo_autorizacion' => $resultado['codigo'],
                'mensaje' => $resultado['mensaje'], 'stripe_payment_intent' => $resultado['payment_intent'] ?? null, 'fecha' => now(),
            ]);
        }
        $plan = $this->crearPlan([
            'id_proforma' => $proforma->id_proforma, 'nro_cuotas' => (int) $d['nro_cuotas'],
            'frecuencia' => $d['frecuencia'] ?? 'mensual', 'fecha_inicio' => now()->toDateString(),
        ], $actor);
        $cuota = $plan->cuotas()->orderBy('nro')->first();
        $cuota->update(['estado' => 'pagada', 'fecha_pago' => now()]);

        return TransaccionPago::create([
            'id_usuario' => $actor->id_usuario, 'id_metodo' => $metodo->id_metodo, 'id_proforma' => $proforma->id_proforma,
            'id_cuota' => $cuota->id_cuota, 'monto' => $cuota->monto, 'estado' => 'aprobada', 'codigo_autorizacion' => $resultado['codigo'],
            'mensaje' => $resultado['mensaje'], 'stripe_payment_intent' => $resultado['payment_intent'] ?? null, 'fecha' => now(),
        ]);
    }

    /** Pago aprobado: el personal ya puede programar las pautas (si el material está aprobado). */
    private function avisarPago(Proforma $proforma, string $concepto, float $monto, Usuario $actor): void
    {
        $situacion = $proforma->situacionPago();
        $campania = $proforma->campania()->value('nombre');
        $siguiente = $situacion['habilita_pautas']
            ? ' Ya se pueden programar sus pautas'.($campania ? " (campaña «{$campania}»)" : '').' en cuanto su material esté aprobado.'
            : '';
        $this->avisos->alPersonal('pautas.crear', "Pago recibido · {$proforma->nro_proforma}",
            'Se aprobó el pago '.$concepto.' por Bs '.number_format($monto, 2, ',', '.').'. '.$situacion['detalle'].$siguiente, $actor);
    }

    private function obtenerMetodoValido(int $idMetodo, Usuario $actor): MetodoPago
    {
        $metodo = MetodoPago::find($idMetodo);
        if (! $metodo) {
            throw new NegocioException('El método de pago seleccionado no existe.');
        }
        $this->metodos->exigirAcceso($metodo, $actor);
        if (! $metodo->activo) {
            throw new NegocioException('El método de pago seleccionado está inactivo.');
        }

        return $metodo;
    }

    private function exigirAccesoPlan(PlanPago $plan, Usuario $actor): void
    {
        if ($actor->esPersonal()) {
            return;
        }
        $esSuyo = $plan->id_usuario === $actor->id_usuario
            || $actor->esSuProforma($plan->proforma);
        if (! $esSuyo) {
            throw new NegocioException('No tiene acceso a este plan de pagos.', 403);
        }
    }

    private function marcarCuotasVencidas(): void
    {
        Cuota::where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => 'vencida']);
    }
}

