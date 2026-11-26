<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Paquete;
use App\Models\PaqueteItem;
use App\Models\Tarifa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** CU3 - Paquetes publicitarios (portado de NPaquete: descuento y precio_total). */
class PaqueteService
{
    private const RELACIONES = [
        'items.tarifa:id_tarifa,monto,unidad_cobro,duracion_seg,id_servicio,activo',
        'items.tarifa.servicio:id_servicio,nombre,tipo',
    ];

    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return Paquete::query()
            ->with(self::RELACIONES)
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nombre', 'ilike', "%{$v}%")
                ->orWhere('descripcion', 'ilike', "%{$v}%")))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('id_paquete')
            ->paginate($porPagina);
    }

    public function obtener(Paquete $paquete): Paquete
    {
        return $paquete->load(self::RELACIONES);
    }

    public function crear(array $d): Paquete
    {
        $this->validarNombreUnico($d['nombre'], 0);
        $descuento = round((float) ($d['descuento'] ?? 0), 2);
        $precioTotal = $this->validarYCalcularTotal($d['items'], $descuento);

        return DB::transaction(function () use ($d, $descuento, $precioTotal) {
            $paquete = Paquete::create([
                'nombre' => $d['nombre'],
                'descripcion' => $d['descripcion'] ?? null,
                'descuento' => $descuento,
                'precio_total' => $precioTotal,
                'activo' => true,
            ]);

            foreach ($d['items'] as $item) {
                PaqueteItem::create([
                    'id_paquete' => $paquete->id_paquete,
                    'id_tarifa' => (int) $item['id_tarifa'],
                    'cantidad' => (int) $item['cantidad'],
                ]);
            }

            return $paquete->load(self::RELACIONES);
        });
    }

    public function actualizar(Paquete $paquete, array $d): Paquete
    {
        $this->validarNombreUnico($d['nombre'], $paquete->id_paquete);
        $descuento = round((float) ($d['descuento'] ?? 0), 2);
        $precioTotal = $this->validarYCalcularTotal($d['items'], $descuento);

        return DB::transaction(function () use ($paquete, $d, $descuento, $precioTotal) {
            $paquete->update([
                'nombre' => $d['nombre'],
                'descripcion' => $d['descripcion'] ?? null,
                'descuento' => $descuento,
                'precio_total' => $precioTotal,
            ]);

            DB::table('paquete_item')->where('id_paquete', $paquete->id_paquete)->delete();
            foreach ($d['items'] as $item) {
                PaqueteItem::create([
                    'id_paquete' => $paquete->id_paquete,
                    'id_tarifa' => (int) $item['id_tarifa'],
                    'cantidad' => (int) $item['cantidad'],
                ]);
            }

            return $paquete->load(self::RELACIONES);
        });
    }

    public function cambiarEstado(Paquete $paquete, bool $activo): Paquete
    {
        if ($paquete->activo === $activo) {
            throw new NegocioException($activo
                ? "El paquete {$paquete->nombre} ya está activo."
                : "El paquete {$paquete->nombre} ya está dado de baja.");
        }
        if ($activo) {
            $inactivas = $paquete->items()->whereHas('tarifa', fn ($q) => $q->where('activo', false))->count();
            if ($inactivas > 0) {
                throw new NegocioException("No se puede reactivar el paquete {$paquete->nombre}: incluye {$inactivas} tarifa(s) deshabilitada(s). Modifique el paquete primero.");
            }
        }
        $paquete->update(['activo' => $activo]);

        return $paquete->load(self::RELACIONES);
    }

    private function validarNombreUnico(string $nombre, int $idPaquete): void
    {
        $existe = Paquete::whereRaw('lower(nombre) = lower(?)', [$nombre])
            ->where('id_paquete', '<>', $idPaquete)
            ->exists();
        if ($existe) {
            throw new NegocioException("Ya existe un paquete con el nombre «{$nombre}».");
        }
    }

    private function validarYCalcularTotal(array $items, float $descuento): float
    {
        if ($descuento < 0 || $descuento > 100) {
            throw new NegocioException('El porcentaje de descuento debe estar entre 0 y 100.');
        }
        if (count($items) === 0) {
            throw new NegocioException('El paquete debe incluir al menos una tarifa.');
        }

        $vistos = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $idTarifa = (int) $item['id_tarifa'];
            $cantidad = (int) $item['cantidad'];

            if (in_array($idTarifa, $vistos, true)) {
                throw new NegocioException('No puede repetir la misma tarifa dentro de un paquete.');
            }
            $vistos[] = $idTarifa;

            if ($cantidad < 1) {
                throw new NegocioException('La cantidad de cada ítem del paquete debe ser al menos 1.');
            }

            $tarifa = Tarifa::find($idTarifa);
            if (! $tarifa) {
                throw new NegocioException("La tarifa #{$idTarifa} no existe.");
            }
            if (! $tarifa->activo) {
                throw new NegocioException("La tarifa #{$idTarifa} está inactiva.");
            }

            $subtotal += $tarifa->monto * $cantidad;
        }

        return round($subtotal * (1 - $descuento / 100), 2);
    }
}

