<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\DetalleProforma;
use App\Models\EspacioPublicitario;
use App\Models\MaterialPublicitario;
use App\Models\Notificacion;
use App\Models\ProgramacionPauta;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * C2 / CU5: Programación de Pautas Multicanal y Notificaciones (NPauta.java).
 */
class PautaService
{
    /** Listado paginado de pautas con filtros y aislamiento por empresa para rol Cliente. */
    public function listar(array $filtros, int $porPagina = 15, ?Usuario $actor = null): LengthAwarePaginator
    {
        $q = ProgramacionPauta::query()
            ->with([
                'espacio.canal:id_canal,nombre,tipo',
                'material:id_material,nombre_archivo,tipo,duracion_seg,id_campania',
                'material.campania:id_campania,nombre,id_proforma',
                'material.campania.proforma.empresa:id_empresa,razon_social',
                'detalle.tarifa.servicio:id_servicio,nombre',
                'detalle.paquete:id_paquete,nombre',
                'usuario:id_usuario,nombre_completo',
            ]);

        if ($actor?->esCliente()) {
            $idEmpresa = $actor->idEmpresa() ?? 0;
            $q->whereHas('material.campania.proforma', fn (Builder $b) => $b->where('id_empresa', $idEmpresa));
        }

        if (! empty($filtros['estado']) && in_array($filtros['estado'], ProgramacionPauta::ESTADOS, true)) {
            $q->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['id_campania'])) {
            $q->whereHas('material', fn (Builder $b) => $b->where('id_campania', (int) $filtros['id_campania']));
        }

        if (! empty($filtros['id_canal'])) {
            $q->whereHas('espacio', fn (Builder $b) => $b->where('id_canal', (int) $filtros['id_canal']));
        }

        if (! empty($filtros['q'])) {
            $busqueda = '%'.trim($filtros['q']).'%';
            $q->where(fn (Builder $b) => $b
                ->where('url_publicacion', 'ilike', $busqueda)
                ->orWhereHas('material', fn (Builder $bm) => $bm->where('nombre_archivo', 'ilike', $busqueda))
                ->orWhereHas('material.campania', fn (Builder $bc) => $bc->where('nombre', 'ilike', $busqueda))
            );
        }

        return $q->orderByDesc('id_programacion')->paginate($porPagina);
    }

    /** RF10: Programación de una pauta publicitaria vinculando material, espacio e ítem contratado. */
    public function programar(array $datos, Usuario $actor): ProgramacionPauta
    {
        // 1. Material aprobado
        $material = MaterialPublicitario::with('campania.proforma')->findOrFail($datos['id_material']);
        if ($material->estado !== MaterialPublicitario::ESTADO_APROBADO) {
            throw new NegocioException("El material «{$material->nombre_archivo}» está «{$material->estado}»; solo se pueden programar piezas aprobadas.");
        }

        if (! in_array($material->campania->estado, ['activa', 'pausada'], true)) {
            throw new NegocioException("La campaña «{$material->campania->nombre}» no está activa.");
        }

        // 2. Espacio disponible
        $espacio = EspacioPublicitario::with('canal')->findOrFail($datos['id_espacio']);
        if (! $espacio->estaDisponible()) {
            throw new NegocioException("El espacio {$espacio->id_espacio} se encuentra {$espacio->estado}; solo se pueden ocupar espacios disponibles.");
        }

        if ($espacio->fecha < now()->toDateString()) {
            throw new NegocioException('No se puede programar en un espacio con fecha pasada.');
        }

        // 3. Detalle contratado
        $detalle = DetalleProforma::findOrFail($datos['id_detalle']);
        if ((int) $detalle->id_proforma !== (int) $material->campania->id_proforma) {
            throw new NegocioException('El ítem de proforma seleccionado no corresponde a la campaña del material.');
        }

        return DB::transaction(function () use ($espacio, $material, $detalle, $actor) {
            // Ocupar el espacio
            $espacio->update(['estado' => EspacioPublicitario::ESTADO_OCUPADO]);

            // Crear la programación
            $pauta = ProgramacionPauta::create([
                'id_espacio' => $espacio->id_espacio,
                'id_material' => $material->id_material,
                'id_detalle' => $detalle->id_detalle,
                'id_usuario' => $actor->id_usuario,
                'estado' => ProgramacionPauta::ESTADO_PROGRAMADA,
                'fecha_programacion' => now(),
            ]);

            // RF11: Notificación automática a los encargados / cliente
            $canalNombre = $espacio->canal->nombre;
            $titulo = "Nueva pauta programada: {$material->nombre_archivo}";
            $mensaje = "Se ha programado la emisión de la pieza publicitaria «{$material->nombre_archivo}» en el canal {$canalNombre} para el día {$espacio->fecha} en el horario {$espacio->hora_inicio} - {$espacio->hora_fin}.";

            // Notificar al usuario que subió el material (si existe)
            if ($material->id_usuario) {
                Notificacion::create([
                    'id_usuario' => $material->id_usuario,
                    'id_programacion' => $pauta->id_programacion,
                    'medio' => Notificacion::MEDIO_SISTEMA,
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'leida' => false,
                    'fecha_envio' => now(),
                ]);
            }

            return $pauta;
        });
    }

    /** Reprogramar pauta a un nuevo espacio. */
    public function reprogramar(ProgramacionPauta $pauta, int $nuevoIdEspacio, Usuario $actor): ProgramacionPauta
    {
        if (! $pauta->estaProgramada()) {
            throw new NegocioException("La pauta {$pauta->id_programacion} está «{$pauta->estado}»; solo se pueden reprogramar pautas en estado programada.");
        }

        if ((int) $pauta->id_espacio === $nuevoIdEspacio) {
            return $pauta;
        }

        $nuevoEspacio = EspacioPublicitario::findOrFail($nuevoIdEspacio);
        if (! $nuevoEspacio->estaDisponible()) {
            throw new NegocioException("El espacio seleccionado {$nuevoEspacio->id_espacio} se encuentra {$nuevoEspacio->estado}.");
        }

        return DB::transaction(function () use ($pauta, $nuevoEspacio) {
            // Liberar espacio anterior
            $pauta->espacio()->update(['estado' => EspacioPublicitario::ESTADO_DISPONIBLE]);

            // Ocupar nuevo espacio
            $nuevoEspacio->update(['estado' => EspacioPublicitario::ESTADO_OCUPADO]);

            $pauta->update(['id_espacio' => $nuevoEspacio->id_espacio]);

            return $pauta;
        });
    }

    /** Registrar emisión o publicación en vivo con URL del contenido. */
    public function emitir(ProgramacionPauta $pauta, ?string $urlPublicacion, Usuario $actor): ProgramacionPauta
    {
        if (! $pauta->estaProgramada()) {
            throw new NegocioException("La pauta {$pauta->id_programacion} ya se encuentra «{$pauta->estado}».");
        }

        $pauta->update([
            'estado' => ProgramacionPauta::ESTADO_EMITIDA,
            'url_publicacion' => $urlPublicacion ?: null,
        ]);

        return $pauta;
    }

    /** Cancelar pauta y liberar el espacio publicitario. */
    public function cancelar(ProgramacionPauta $pauta, Usuario $actor): ProgramacionPauta
    {
        if (! $pauta->estaProgramada()) {
            throw new NegocioException("La pauta {$pauta->id_programacion} está «{$pauta->estado}» y no se puede cancelar.");
        }

        return DB::transaction(function () use ($pauta) {
            // Liberar espacio
            $pauta->espacio()->update(['estado' => EspacioPublicitario::ESTADO_DISPONIBLE]);

            // Actualizar estado
            $pauta->update(['estado' => ProgramacionPauta::ESTADO_CANCELADA]);

            return $pauta;
        });
    }
}
