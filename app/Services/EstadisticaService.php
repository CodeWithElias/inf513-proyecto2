<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\EspacioPublicitario;
use App\Models\MaterialPublicitario;
use App\Models\MetricaPublicacion;
use App\Models\ProgramacionPauta;
use App\Models\Proforma;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * C4: Servicio analítico y estadístico para el Dashboard Central.
 */
class EstadisticaService
{
    /** Métricas de negocio completas respetando aislamiento para Clientes. */
    public function obtenerEstadisticasNegocio(?Usuario $actor = null): array
    {
        $esCliente = $actor?->esCliente() ?? false;
        $idEmpresa = $actor?->idEmpresa() ?? 0;

        // 1. KPIs Generales
        $qPagos = DB::table('transaccion_pago')->where('estado', 'aprobada');
        $qProformas = Proforma::query();
        $qCampanias = Campania::query();
        $qPautas = ProgramacionPauta::query();
        $qMaterial = MaterialPublicitario::query();

        if ($esCliente) {
            $qPagos->where('id_usuario', $actor->id_usuario);
            $qProformas->where('id_empresa', $idEmpresa);
            $qCampanias->whereHas('proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
            $qPautas->whereHas('material.campania.proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
            $qMaterial->whereHas('campania.proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
        }

        $ingresosTotales = (float) $qPagos->sum('monto');
        $proformasAprobadas = (clone $qProformas)->where('estado', 'aprobada')->count();
        $campaniasActivas = (clone $qCampanias)->where('estado', 'activa')->count();
        $pautasEmitidas = (clone $qPautas)->where('estado', ProgramacionPauta::ESTADO_EMITIDA)->count();
        $materialAprobado = (clone $qMaterial)->where('estado', MaterialPublicitario::ESTADO_APROBADO)->count();

        // Tasa de ocupación de espacios publicitarios
        $totalEspacios = EspacioPublicitario::count();
        $espaciosOcupados = EspacioPublicitario::where('estado', EspacioPublicitario::ESTADO_OCUPADO)->count();
        $tasaOcupacion = $totalEspacios > 0 ? round(($espaciosOcupados / $totalEspacios) * 100, 1) : 0;

        // 2. Ingresos mensuales (últimos 6 meses)
        $ingresosMensuales = DB::table('transaccion_pago')
            ->where('estado', 'aprobada')
            ->when($esCliente, fn ($b) => $b->where('id_usuario', $actor->id_usuario))
            ->where('fecha', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("to_char(fecha, 'YYYY-MM') as mes, SUM(monto) as total")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->map(fn ($r) => [
                'mes' => $r->mes,
                'total' => (float) $r->total,
            ])
            ->all();

        // 3. Proformas agrupadas por estado
        $proformasPorEstado = (clone $qProformas)
            ->selectRaw('estado, COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')
            ->groupBy('estado')
            ->get()
            ->map(fn ($p) => [
                'estado' => $p->estado,
                'cantidad' => (int) $p->cantidad,
                'monto' => (float) $p->monto,
            ])
            ->all();

        // 4. Ocupación de canales de difusión
        $canalesOcupacion = DB::table('canal_difusion as c')
            ->where('c.activo', true)
            ->leftJoin('espacio_publicitario as e', 'e.id_canal', '=', 'c.id_canal')
            ->selectRaw("
                c.nombre as canal,
                c.tipo,
                COUNT(e.id_espacio) as total_espacios,
                COUNT(CASE WHEN e.estado = 'ocupado' THEN 1 END) as ocupados,
                COUNT(CASE WHEN e.estado = 'disponible' THEN 1 END) as disponibles
            ")
            ->groupBy('c.id_canal', 'c.nombre', 'c.tipo')
            ->orderBy('c.nombre')
            ->limit(8)
            ->get()
            ->all();

        // 5. Top servicios publicitarios más contratados
        $topServicios = DB::table('detalle_proforma as dp')
            ->join('tarifa as t', 't.id_tarifa', '=', 'dp.id_tarifa')
            ->join('servicio_publicitario as s', 's.id_servicio', '=', 't.id_servicio')
            // El Cliente ve solo lo que contrató su empresa (no el volumen de otras empresas)
            ->when($esCliente, fn ($b) => $b->join('proforma as pf', 'pf.id_proforma', '=', 'dp.id_proforma')->where('pf.id_empresa', $idEmpresa))
            ->selectRaw('s.nombre, COUNT(*) as contrataciones, SUM(dp.subtotal) as volumen_bs')
            ->groupBy('s.id_servicio', 's.nombre')
            ->orderByDesc('contrataciones')
            ->limit(5)
            ->get()
            ->all();

        // 6. Impacto acumulado en redes
        $qImpacto = MetricaPublicacion::query();
        if ($esCliente) {
            $qImpacto->whereHas('pauta.material.campania.proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
        }

        $impacto = [
            'alcance' => (int) (clone $qImpacto)->sum('alcance'),
            'impresiones' => (int) (clone $qImpacto)->sum('impresiones'),
            'interacciones' => (int) (clone $qImpacto)->sum('interacciones'),
            'clics' => (int) (clone $qImpacto)->sum('clics'),
        ];

        return [
            'kpis' => [
                'ingresos_totales' => $ingresosTotales,
                'campanias_activas' => $campaniasActivas,
                'proformas_aprobadas' => $proformasAprobadas,
                'pautas_emitidas' => $pautasEmitidas,
                'material_aprobado' => $materialAprobado,
                'tasa_ocupacion' => $tasaOcupacion,
            ],
            'ingresos_mensuales' => $ingresosMensuales,
            'proformas_por_estado' => $proformasPorEstado,
            'canales_ocupacion' => $canalesOcupacion,
            'top_servicios' => $topServicios,
            'impacto' => $impacto,
        ];
    }
}
