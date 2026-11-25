<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\ServicioPublicitario;
use App\Models\Tarifa;
use App\Models\UnidadUniversitaria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** CU3 - Tarifas con vigencia (portado de NTarifa). */
class TarifaService
{
    private const RELACIONES = [
        'servicio:id_servicio,nombre,tipo,activo',
        'unidad:id_unidad,nombre',
    ];

    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        $hoy = now()->toDateString();

        return Tarifa::query()
            ->with(self::RELACIONES)
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('unidad_cobro', 'ilike', "%{$v}%")
                ->orWhereHas('servicio', fn ($s) => $s->where('nombre', 'ilike', "%{$v}%"))))
            ->when($f['id_servicio'] ?? null, fn ($q, $v) => $q->where('id_servicio', $v))
            ->when($f['id_unidad'] ?? null, fn ($q, $v) => $q->where('id_unidad', $v))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->when(($f['vigente'] ?? null) === 'true', fn ($q) => $q
                ->where('activo', true)
                ->where(fn ($w) => $w->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $hoy))
                ->where(fn ($w) => $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy)))
            ->orderBy('id_tarifa')
            ->paginate($porPagina);
    }

    public function crear(array $d): Tarifa
    {
        $this->validarReglas($d);

        return Tarifa::create([
            'id_servicio' => $d['id_servicio'],
            'id_unidad' => $d['id_unidad'] ?? null,
            'monto' => round((float) $d['monto'], 2),
            'unidad_cobro' => $d['unidad_cobro'] ?? null,
            'vigente_desde' => $d['vigente_desde'] ?? null,
            'vigente_hasta' => $d['vigente_hasta'] ?? null,
            'duracion_seg' => $d['duracion_seg'] ?? null,
            'activo' => true,
        ])->load(self::RELACIONES);
    }

    public function actualizar(Tarifa $tarifa, array $d): Tarifa
    {
        $this->validarReglas($d);

        $tarifa->update([
            'id_servicio' => $d['id_servicio'],
            'id_unidad' => $d['id_unidad'] ?? null,
            'monto' => round((float) $d['monto'], 2),
            'unidad_cobro' => $d['unidad_cobro'] ?? null,
            'vigente_desde' => $d['vigente_desde'] ?? null,
            'vigente_hasta' => $d['vigente_hasta'] ?? null,
            'duracion_seg' => $d['duracion_seg'] ?? null,
        ]);

        return $tarifa->load(self::RELACIONES);
    }

    public function cambiarEstado(Tarifa $tarifa, bool $activo): Tarifa
    {
        if ($tarifa->activo === $activo) {
            throw new NegocioException($activo
                ? 'La tarifa seleccionada ya está activa.'
                : 'La tarifa seleccionada ya está dada de baja.');
        }
        $tarifa->update(['activo' => $activo]);

        return $tarifa->load(self::RELACIONES);
    }

    private function validarReglas(array $d): void
    {
        $servicio = ServicioPublicitario::find($d['id_servicio']);
        if (! $servicio) {
            throw new NegocioException('El servicio publicitario seleccionado no existe.');
        }
        if (! $servicio->activo) {
            throw new NegocioException("El servicio {$servicio->nombre} está inactivo.");
        }
        if (! empty($d['id_unidad'])) {
            $unidad = UnidadUniversitaria::find($d['id_unidad']);
            if (! $unidad) {
                throw new NegocioException('La unidad universitaria seleccionada no existe.');
            }
            if (! $unidad->activo) {
                throw new NegocioException("La unidad {$unidad->nombre} está inactiva.");
            }
        }
        if ((float) $d['monto'] <= 0) {
            throw new NegocioException('El monto de la tarifa debe ser mayor a cero.');
        }
        if (! empty($d['vigente_desde']) && ! empty($d['vigente_hasta']) && $d['vigente_hasta'] < $d['vigente_desde']) {
            throw new NegocioException('La fecha de fin de vigencia no puede ser anterior a la fecha de inicio.');
        }
    }
}

