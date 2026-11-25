<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\MetricaPublicacion;
use App\Models\ProgramacionPauta;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * C3 / CU7: Métricas de Publicación y Análisis de Impacto (NMetrica.java).
 */
class MetricaService
{
    /** Listado paginado de métricas de impacto con aislamiento por empresa para Clientes. */
    public function listar(array $filtros, int $porPagina = 15, ?Usuario $actor = null): LengthAwarePaginator
    {
        $q = MetricaPublicacion::query()
            ->with([
                'pauta.espacio.canal:id_canal,nombre,tipo',
                'pauta.material:id_material,nombre_archivo,tipo',
                'pauta.material.campania:id_campania,nombre',
                'pauta.material.campania.proforma.empresa:id_empresa,razon_social',
            ]);

        if ($actor?->esCliente()) {
            $idEmpresa = $actor->idEmpresa() ?? 0;
            $q->whereHas('pauta.material.campania.proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
        }

        if (! empty($filtros['id_programacion'])) {
            $q->where('id_programacion', (int) $filtros['id_programacion']);
        }

        if (! empty($filtros['id_campania'])) {
            $q->whereHas('pauta.material', fn (Builder $b) => $b->where('id_campania', (int) $filtros['id_campania']));
        }

        return $q->orderByDesc('fecha_medicion')->paginate($porPagina);
    }

    /** RF13: Registrar una nueva medición acumulada de impacto para una pauta. */
    public function registrar(array $datos, Usuario $actor): MetricaPublicacion
    {
        $pauta = ProgramacionPauta::with('material.campania.proforma')->findOrFail($datos['id_programacion']);

        if (! in_array($pauta->estado, [ProgramacionPauta::ESTADO_EMITIDA, ProgramacionPauta::ESTADO_PROGRAMADA], true)) {
            throw new NegocioException("La pauta #{$pauta->id_programacion} está {$pauta->estado}; solo se miden pautas emitidas o programadas.");
        }

        $alcance = max(0, (int) ($datos['alcance'] ?? 0));
        $impresiones = max(0, (int) ($datos['impresiones'] ?? 0));
        $interacciones = max(0, (int) ($datos['interacciones'] ?? 0));
        $clics = max(0, (int) ($datos['clics'] ?? 0));
        $compartidos = max(0, (int) ($datos['compartidos'] ?? 0));

        // Reglas de coherencia matemática de NMetrica.java
        if ($impresiones > 0) {
            if ($alcance > $impresiones) {
                throw new NegocioException("El alcance ({$alcance}) no puede superar el total de impresiones ({$impresiones}).");
            }
            if ($clics > $impresiones) {
                throw new NegocioException("Los clics ({$clics}) no pueden superar el total de impresiones ({$impresiones}).");
            }
        }

        if ($interacciones > 0 && $compartidos > $interacciones) {
            throw new NegocioException("Los compartidos ({$compartidos}) no pueden superar el total de interacciones ({$interacciones}).");
        }

        return MetricaPublicacion::create([
            'id_programacion' => $pauta->id_programacion,
            'fecha_medicion' => now(),
            'alcance' => $alcance,
            'impresiones' => $impresiones,
            'interacciones' => $interacciones,
            'clics' => $clics,
            'compartidos' => $compartidos,
        ]);
    }

    /** Resumen de métricas acumuladas por pauta o global. */
    public function resumen(?Usuario $actor = null): array
    {
        $q = MetricaPublicacion::query();

        if ($actor?->esCliente()) {
            $idEmpresa = $actor->idEmpresa() ?? 0;
            $q->whereHas('pauta.material.campania.proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
        }

        return [
            'total_alcance' => (int) $q->sum('alcance'),
            'total_impresiones' => (int) $q->sum('impresiones'),
            'total_interacciones' => (int) $q->sum('interacciones'),
            'total_clics' => (int) $q->sum('clics'),
            'total_compartidos' => (int) $q->sum('compartidos'),
            'total_mediciones' => $q->count(),
        ];
    }
}
