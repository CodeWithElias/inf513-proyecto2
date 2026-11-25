<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\CanalDifusion;
use App\Models\EspacioPublicitario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * C2 / CU5: Gestión de Espacios Publicitarios (NEspacio.java).
 */
class EspacioService
{
    /** Listado de espacios con filtros por canal, fecha y disponibilidad. */
    public function listar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        $q = EspacioPublicitario::query()
            ->with([
                'canal:id_canal,nombre,tipo,activo',
                'pauta.material:id_material,nombre_archivo,tipo,duracion_seg',
                'pauta.material.campania:id_campania,nombre',
            ]);

        if (! empty($filtros['id_canal'])) {
            $q->where('id_canal', (int) $filtros['id_canal']);
        }

        if (! empty($filtros['estado']) && in_array($filtros['estado'], EspacioPublicitario::ESTADOS, true)) {
            $q->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['fecha'])) {
            $q->where('fecha', $filtros['fecha']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $q->where('fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $q->where('fecha', '<=', $filtros['fecha_hasta']);
        }

        return $q->orderBy('fecha')->orderBy('hora_inicio')->paginate($porPagina);
    }

    /** RF10: Registro de nuevo espacio publicitario con validación de no superposición. */
    public function crear(array $datos): EspacioPublicitario
    {
        $canal = CanalDifusion::findOrFail($datos['id_canal']);
        if (! $canal->activo) {
            throw new NegocioException("El canal «{$canal->nombre}» está inactivo; no se pueden crear espacios en canales dados de baja.");
        }

        $fecha = $datos['fecha'];
        if ($fecha < now()->toDateString()) {
            throw new NegocioException('No se pueden crear espacios publicitarios en fechas pasadas.');
        }

        $inicio = $datos['hora_inicio'];
        $fin = $datos['hora_fin'];
        if ($inicio >= $fin) {
            throw new NegocioException('La hora de inicio debe ser anterior a la hora de fin.');
        }

        $this->validarNoSuperposicion($canal->id_canal, $fecha, $inicio, $fin);

        return EspacioPublicitario::create([
            'id_canal' => $canal->id_canal,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $datos['estado'] ?? EspacioPublicitario::ESTADO_DISPONIBLE,
        ]);
    }

    /** Modificación de horario o estado (solo si no está ocupado). */
    public function actualizar(EspacioPublicitario $espacio, array $datos): EspacioPublicitario
    {
        if ($espacio->estaOcupado()) {
            throw new NegocioException("El espacio {$espacio->id_espacio} está ocupado por una pauta; no se puede alterar su programación.");
        }

        $idCanal = $datos['id_canal'] ?? $espacio->id_canal;
        $fecha = $datos['fecha'] ?? $espacio->fecha;
        $inicio = $datos['hora_inicio'] ?? $espacio->hora_inicio;
        $fin = $datos['hora_fin'] ?? $espacio->hora_fin;

        if ($inicio >= $fin) {
            throw new NegocioException('La hora de inicio debe ser anterior a la hora de fin.');
        }

        $this->validarNoSuperposicion($idCanal, $fecha, $inicio, $fin, $espacio->id_espacio);

        $espacio->update([
            'id_canal' => $idCanal,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $datos['estado'] ?? $espacio->estado,
        ]);

        return $espacio;
    }

    /** Eliminación de espacio (solo disponible o bloqueado). */
    public function eliminar(EspacioPublicitario $espacio): void
    {
        if ($espacio->estaOcupado()) {
            throw new NegocioException("No se puede eliminar el espacio {$espacio->id_espacio}: está ocupado por una pauta activa.");
        }

        $espacio->delete();
    }

    /** Comprueba que en ese canal y fecha no exista otro intervalo solapado. */
    private function validarNoSuperposicion(int $idCanal, string $fecha, string $inicio, string $fin, ?int $excluirId = null): void
    {
        $solapado = EspacioPublicitario::query()
            ->where('id_canal', $idCanal)
            ->where('fecha', $fecha)
            ->when($excluirId, fn (Builder $b) => $b->where('id_espacio', '!=', $excluirId))
            ->where(function (Builder $b) use ($inicio, $fin) {
                $b->where('hora_inicio', '<', $fin)
                    ->where('hora_fin', '>', $inicio);
            })
            ->first();

        if ($solapado) {
            throw new NegocioException(
                "Existe superposición de horario en esa fecha: el espacio {$solapado->id_espacio} ya cubre de {$solapado->hora_inicio} a {$solapado->hora_fin}."
            );
        }
    }
}
