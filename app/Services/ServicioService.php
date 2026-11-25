<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\ServicioPublicitario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** CU3 - Servicios publicitarios (portado de NServicio). */
class ServicioService
{
    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return ServicioPublicitario::query()
            ->withCount('tarifas')
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nombre', 'ilike', "%{$v}%")
                ->orWhere('descripcion', 'ilike', "%{$v}%")))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('id_servicio')
            ->paginate($porPagina);
    }

    public function crear(array $d): ServicioPublicitario
    {
        $this->validarNombreUnico($d['nombre'], 0);

        return ServicioPublicitario::create([
            'nombre' => $d['nombre'],
            'tipo' => $d['tipo'] ?? null,
            'descripcion' => $d['descripcion'] ?? null,
            'activo' => true,
        ]);
    }

    public function actualizar(ServicioPublicitario $servicio, array $d): ServicioPublicitario
    {
        $this->validarNombreUnico($d['nombre'], $servicio->id_servicio);

        $servicio->update([
            'nombre' => $d['nombre'],
            'tipo' => $d['tipo'] ?? null,
            'descripcion' => $d['descripcion'] ?? null,
        ]);

        return $servicio;
    }

    public function cambiarEstado(ServicioPublicitario $servicio, bool $activo): ServicioPublicitario
    {
        if ($servicio->activo === $activo) {
            throw new NegocioException($activo
                ? "El servicio {$servicio->nombre} ya está activo."
                : "El servicio {$servicio->nombre} ya está dado de baja.");
        }
        $servicio->update(['activo' => $activo]);

        return $servicio;
    }

    private function validarNombreUnico(string $nombre, int $idServicio): void
    {
        $existe = ServicioPublicitario::whereRaw('lower(nombre) = lower(?)', [$nombre])
            ->where('id_servicio', '<>', $idServicio)
            ->exists();
        if ($existe) {
            throw new NegocioException("Ya existe un servicio publicitario con el nombre «{$nombre}».");
        }
    }
}

