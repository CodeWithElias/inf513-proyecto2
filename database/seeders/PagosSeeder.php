<?php

namespace Database\Seeders;

use App\Models\Cuota;
use App\Models\MetodoPago;
use App\Models\PlanPago;
use App\Models\Proforma;
use App\Models\TransaccionPago;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * B7 - Datos de prueba para el módulo de Pagos (idempotente).
 * Crea métodos de pago, transacciones de ejemplo y un plan de 3 cuotas
 * usando usuarios Cliente y proformas aprobadas ya existentes en db_grupo13sa.
 */
class PagosSeeder extends Seeder
{
    public function run(): void
    {
        if (MetodoPago::exists() || TransaccionPago::exists()) {
            return;
        }

        $clientes = Usuario::query()
            ->whereHas('rol', fn ($r) => $r->where('nombre', 'Cliente'))
            ->where('activo', true)
            ->orderBy('id_usuario')
            ->get();

        if ($clientes->isEmpty()) {
            return;
        }

        $cliente1 = $clientes->first();
        $cliente2 = $clientes->skip(1)->first() ?? $cliente1;

        $metodoVisa = MetodoPago::create([
            'id_usuario' => $cliente1->id_usuario,
            'tipo' => 'tarjeta',
            'marca' => 'Visa',
            'ultimos4' => '4242',
            'titular' => $cliente1->nombre_completo,
            'mes_exp' => 12,
            'anio_exp' => (int) now()->format('Y') + 3,
            'token_simulado' => 'tok_seed_visa_4242',
            'predeterminado' => true,
            'activo' => true,
        ]);

        $metodoRechazo = MetodoPago::create([
            'id_usuario' => $cliente1->id_usuario,
            'tipo' => 'tarjeta',
            'marca' => 'Mastercard',
            'ultimos4' => '0002',
            'titular' => $cliente1->nombre_completo,
            'mes_exp' => 10,
            'anio_exp' => (int) now()->format('Y') + 2,
            'token_simulado' => 'tok_seed_mc_0002',
            'predeterminado' => false,
            'activo' => true,
        ]);

        $metodoQr = MetodoPago::create([
            'id_usuario' => $cliente2->id_usuario,
            'tipo' => 'qr',
            'marca' => 'QR Simple',
            'ultimos4' => null,
            'titular' => $cliente2->nombre_completo,
            'mes_exp' => null,
            'anio_exp' => null,
            'token_simulado' => 'tok_seed_qr_simple',
            'predeterminado' => true,
            'activo' => true,
        ]);

        $proformasAprobadas = Proforma::where('estado', 'aprobada')
            ->where('total', '>', 0)
            ->orderBy('id_proforma')
            ->get();

        if ($proformasAprobadas->isEmpty()) {
            return;
        }

        // 1) Pago único aprobado + intento rechazado previo en la primera proforma
        $p1 = $proformasAprobadas->first();
        TransaccionPago::create([
            'id_usuario' => $cliente1->id_usuario,
            'id_metodo' => $metodoRechazo->id_metodo,
            'id_proforma' => $p1->id_proforma,
            'id_cuota' => null,
            'monto' => $p1->total,
            'estado' => 'rechazada',
            'codigo_autorizacion' => null,
            'mensaje' => 'Fondos insuficientes.',
            'fecha' => now()->subDays(10),
        ]);

        TransaccionPago::create([
            'id_usuario' => $cliente1->id_usuario,
            'id_metodo' => $metodoVisa->id_metodo,
            'id_proforma' => $p1->id_proforma,
            'id_cuota' => null,
            'monto' => $p1->total,
            'estado' => 'aprobada',
            'codigo_autorizacion' => 'AUT-SEED01',
            'mensaje' => 'Pago aprobado por pasarela simulada.',
            'fecha' => now()->subDays(9),
        ]);

        // 2) Plan de 3 cuotas mensuales sobre la segunda proforma aprobada (1 cuota pagada, 2 pendientes)
        $p2 = $proformasAprobadas->skip(1)->first();
        if ($p2) {
            $base = floor(($p2->total / 3) * 100) / 100;
            $ultima = round($p2->total - ($base * 2), 2);

            $plan = PlanPago::create([
                'id_usuario' => $cliente2->id_usuario,
                'id_proforma' => $p2->id_proforma,
                'monto_total' => $p2->total,
                'nro_cuotas' => 3,
                'frecuencia' => 'mensual',
                'fecha_inicio' => now()->subDays(20)->toDateString(),
                'estado' => 'activo',
            ]);

            $c1 = Cuota::create([
                'id_plan' => $plan->id_plan,
                'nro' => 1,
                'monto' => $base,
                'fecha_vencimiento' => now()->subDays(20)->toDateString(),
                'estado' => 'pagada',
                'fecha_pago' => now()->subDays(19),
            ]);

            Cuota::create([
                'id_plan' => $plan->id_plan,
                'nro' => 2,
                'monto' => $base,
                'fecha_vencimiento' => now()->addDays(10)->toDateString(),
                'estado' => 'pendiente',
            ]);

            Cuota::create([
                'id_plan' => $plan->id_plan,
                'nro' => 3,
                'monto' => $ultima,
                'fecha_vencimiento' => now()->addDays(40)->toDateString(),
                'estado' => 'pendiente',
            ]);

            TransaccionPago::create([
                'id_usuario' => $cliente2->id_usuario,
                'id_metodo' => $metodoQr->id_metodo,
                'id_proforma' => $p2->id_proforma,
                'id_cuota' => $c1->id_cuota,
                'monto' => $c1->monto,
                'estado' => 'aprobada',
                'codigo_autorizacion' => 'AUT-SEED02',
                'mensaje' => 'Pago aprobado por pasarela simulada.',
                'fecha' => now()->subDays(19),
            ]);
        }
    }
}

