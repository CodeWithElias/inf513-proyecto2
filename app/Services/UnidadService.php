<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\UnidadUniversitaria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** CU1 - Unidades universitarias jerárquicas (portado de NUnidad). */
class UnidadService
{
    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return UnidadUniversitaria::query()
            ->with('padre:id_unidad,nombre')
            ->withCount([
                'usuarios as usuarios_activos' => fn ($w) => $w->where('activo', true),
                'hijas as subunidades_activas' => fn ($w) => $w->where('activo', true),
            ])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nombre', 'ilike', "%{$v}%")->orWhere('tipo', 'ilike', "%{$v}%")))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('id_unidad')
            ->paginate($porPagina);
    }

    public function crear(array $d): UnidadUniversitaria
    {
        $this->validarPadre($d['id_unidad_padre'] ?? null);

        return UnidadUniversitaria::create([
            'nombre' => $d['nombre'],
            'tipo' => $d['tipo'] ?? null,
            'id_unidad_padre' => $d['id_unidad_padre'] ?? null,
        ]);
    }

    public function actualizar(UnidadUniversitaria $u, array $d): UnidadUniversitaria
    {
        $padre = $d['id_unidad_padre'] ?? null;
        $this->validarPadre($padre);
        if ($padre !== null && in_array((int) $padre, $this->idsDelSubarbol($u->id_unidad), true)) {
            throw new NegocioException('La unidad padre no puede ser la misma unidad ni una de sus subunidades.');
        }
        $u->update(['nombre' => $d['nombre'], 'tipo' => $d['tipo'] ?? null, 'id_unidad_padre' => $padre]);

        return $u->load('padre:id_unidad,nombre');
    }

    public function cambiarEstado(UnidadUniversitaria $u, bool $activo): UnidadUniversitaria
    {
        if ($u->activo === $activo) {
            throw new NegocioException($activo ? "La unidad {$u->nombre} ya está activa." : "La unidad {$u->nombre} ya está dada de baja.");
        }
        if (! $activo) {
            $usuarios = $u->usuarios()->where('activo', true)->count();
            if ($usuarios > 0) {
                throw new NegocioException("No se puede dar de baja la unidad {$u->nombre}: tiene {$usuarios} usuario(s) activo(s) asignado(s). Reasígnelos primero.");
            }
            $hijas = $u->hijas()->where('activo', true)->count();
            if ($hijas > 0) {
                throw new NegocioException("No se puede dar de baja la unidad {$u->nombre}: tiene {$hijas} subunidad(es) activa(s). Délas de baja primero.");
            }
        } elseif ($u->padre && ! $u->padre->activo) {
            throw new NegocioException("No se puede reactivar la unidad {$u->nombre}: su unidad padre ({$u->padre->nombre}) está inactiva. Reactívela primero.");
        }
        $u->update(['activo' => $activo]);

        return $u;
    }

    private function validarPadre(mixed $idPadre): void
    {
        if ($idPadre === null || $idPadre === '') {
            return;
        }
        $padre = UnidadUniversitaria::find($idPadre);
        if (! $padre) {
            throw new NegocioException('La unidad padre seleccionada no existe.');
        }
        if (! $padre->activo) {
            throw new NegocioException("La unidad padre {$padre->nombre} está inactiva.");
        }
    }

    /** IDs de la unidad y todas sus descendientes (CTE recursiva). */
    private function idsDelSubarbol(int $id): array
    {
        return array_map('intval', DB::table(DB::raw('(WITH RECURSIVE sub AS (
                SELECT id_unidad FROM unidad_universitaria WHERE id_unidad = '.$id.'
                UNION ALL
                SELECT u.id_unidad FROM unidad_universitaria u JOIN sub ON u.id_unidad_padre = sub.id_unidad
            ) SELECT id_unidad FROM sub) as t'))->pluck('id_unidad')->all());
    }
}
