<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\CanalDifusion;
use App\Models\Plataforma;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** CU2/CU5 - Plataformas y canales de difusión no sociales (portado de NPlataforma y NCanal). */
class CanalService
{
    public function listarPlataformas(): Collection
    {
        return Plataforma::query()
            ->select('plataforma.*')
            ->selectSub(DB::table('red_social')->selectRaw('count(*)')->whereColumn('red_social.id_plataforma', 'plataforma.id_plataforma'), 'redes')
            ->orderBy('nombre')
            ->get();
    }

    public function crearPlataforma(string $nombre): Plataforma
    {
        return Plataforma::create(['nombre' => $nombre]);
    }

    public function listarCanales(array $f, int $porPagina): LengthAwarePaginator
    {
        return CanalDifusion::query()
            ->where('tipo', '<>', CanalDifusion::RED_SOCIAL)
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('nombre', 'ilike', "%{$v}%"))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('id_canal')
            ->paginate($porPagina);
    }

    public function crearCanal(array $d): CanalDifusion
    {
        $existe = CanalDifusion::whereRaw('lower(nombre) = lower(?)', [$d['nombre']])->exists();
        if ($existe) {
            throw new NegocioException("Ya existe un canal llamado «{$d['nombre']}».");
        }

        return CanalDifusion::create(['tipo' => $d['tipo'], 'nombre' => $d['nombre']])->refresh();
    }

    public function cambiarEstadoCanal(CanalDifusion $canal, bool $activo): CanalDifusion
    {
        if ($canal->tipo === CanalDifusion::RED_SOCIAL) {
            throw new NegocioException('Este canal es una red social; adminístrelo desde Redes institucionales.');
        }
        if ($canal->activo === $activo) {
            throw new NegocioException($activo ? "El canal {$canal->nombre} ya está activo." : "El canal {$canal->nombre} ya está dado de baja.");
        }
        if (! $activo) {
            $programadas = DB::table('programacion_pauta as pp')
                ->join('espacio_publicitario as e', 'e.id_espacio', '=', 'pp.id_espacio')
                ->where('e.id_canal', $canal->id_canal)
                ->where('pp.estado', 'programada')
                ->count();
            if ($programadas > 0) {
                throw new NegocioException("No se puede dar de baja el canal {$canal->nombre}: tiene {$programadas} pauta(s) programada(s). Cancélelas o reprográmelas primero.");
            }
        }
        $canal->update(['activo' => $activo, 'fecha_baja' => $activo ? null : now()->toDateString()]);

        return $canal;
    }
}
