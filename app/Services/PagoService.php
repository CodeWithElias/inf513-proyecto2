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
                    if ($actor->idEmpresa()) {
                        $w->orWhereHas('proforma', fn ($p) => $p->where('id_empresa', $actor->idEmpresa()));
                    }
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
        $resultado = $this->procesarPasarela($metodo);

        return TransaccionPago::create([
            'id_usuario' => $actor->id_usuario,
            'id_metodo' => $metodo->id_metodo,
            'id_proforma' => $proforma->id_proforma,
            'id_cuota' => null,
            'monto' => $proforma->total,
            'estado' => $resultado['estado'],
            'codigo_autorizacion' => $resultado['codigo'],
            'mensaje' => $resultado['mensaje'],
            'fecha' => now(),
        ])->load([
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
                    if ($actor->idEmpresa()) {
                        $w->orWhereHas('proforma', fn ($p) => $p->where('id_empresa', $actor->idEmpresa()));
                    }
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

        $metodo = $this->obtenerMetodoValido((int) $d['id_metodo'], $actor);
        $resultado = $this->procesarPasarela($metodo);

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
                'fecha' => now(),
            ]);

            if ($resultado['estado'] === 'aprobada') {
                $cuota->update([
                    'estado' => 'pagada',
                    'fecha_pago' => now(),
                ]);
            }

            return $tx->load([
                'metodo:id_metodo,tipo,marca,ultimos4,titular',
                'proforma:id_proforma,nro_proforma,total,estado',
                'cuota.plan',
            ]);
        });
    }

    /**
     * Pasarela simulada según especificación:
     * - Tarjeta terminada en 0002 -> rechazada ("Fondos insuficientes")
     * - Tarjeta terminada en 0069 o vencida -> rechazada ("Tarjeta vencida")
     * - Cualquier otra válida -> aprobada con código AUT-XXXXXX
     */
    private function procesarPasarela(MetodoPago $metodo): array
    {
        if ($metodo->tipo === 'tarjeta') {
            if ($metodo->ultimos4 === '0002') {
                return [
                    'estado' => 'rechazada',
                    'codigo' => null,
                    'mensaje' => 'Fondos insuficientes.',
                ];
            }

            $anioActual = (int) now()->format('Y');
            $mesActual = (int) now()->format('n');
            $vencida = $metodo->anio_exp < $anioActual
                || ($metodo->anio_exp === $anioActual && $metodo->mes_exp < $mesActual);

            if ($metodo->ultimos4 === '0069' || $vencida) {
                return [
                    'estado' => 'rechazada',
                    'codigo' => null,
                    'mensaje' => 'Tarjeta vencida.',
                ];
            }
        }

        return [
            'estado' => 'aprobada',
            'codigo' => 'AUT-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'mensaje' => 'Pago aprobado por pasarela simulada.',
        ];
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
            || ($actor->idEmpresa() && $plan->proforma?->id_empresa === $actor->idEmpresa());
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

