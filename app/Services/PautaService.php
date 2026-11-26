<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\DetalleProforma;
use App\Models\EspacioPublicitario;
use App\Models\MaterialPublicitario;
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
    public function __construct(private readonly AvisoService $avisos)
    {
    }

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

        // 1b. Proforma pagada (al contado, plan completado o cuotas al día)
        $pago = $material->campania->proforma->situacionPago();
        if (! $pago['habilita_pautas']) {
            throw new NegocioException("Aún no se pueden programar pautas de la campaña «{$material->campania->nombre}»: la proforma {$material->campania->proforma->nro_proforma} no está pagada. {$pago['detalle']}");
        }

        // 2. Espacio disponible
        $espacio = EspacioPublicitario::with('canal')->findOrFail($datos['id_espacio']);
        if (! $espacio->estaDisponible()) {
            throw new NegocioException("El espacio {$espacio->id_espacio} se encuentra {$espacio->estado}; solo se pueden ocupar espacios disponibles.");
        }
        if (DB::table('contratacion_espacio')->where('id_espacio', $espacio->id_espacio)->whereNull('id_programacion')
            ->where('fecha_reserva', '>=', now()->subHours(ContratacionService::HORAS_RESERVA))->exists()) {
            throw new NegocioException("El espacio {$espacio->id_espacio} está apartado por una contratación en curso; elija otro.");
        }
        if (! $espacio->canal?->activo) {
            throw new NegocioException("El canal «{$espacio->canal?->nombre}» está dado de baja; elija un espacio de un canal activo.");
        }

        if ($espacio->fecha->toDateString() < now()->toDateString()) {
            throw new NegocioException('No se puede programar en un espacio con fecha pasada.');
        }
        $this->validarDentroDeCampania($espacio, $material->campania);

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

            // RF11: Notificación automática al cliente (representantes de la empresa y quien subió el material)
            $this->avisarCliente($pauta, "Nueva pauta programada: {$material->nombre_archivo}",
                "Se programó la emisión de «{$material->nombre_archivo}» en {$this->cuando($espacio)}.", $actor);

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

        $nuevoEspacio = EspacioPublicitario::with('canal')->findOrFail($nuevoIdEspacio);
        if (! $nuevoEspacio->estaDisponible()) {
            throw new NegocioException("El espacio seleccionado {$nuevoEspacio->id_espacio} se encuentra {$nuevoEspacio->estado}.");
        }
        if ($nuevoEspacio->fecha->toDateString() < now()->toDateString()) {
            throw new NegocioException('No se puede reprogramar a un espacio con fecha pasada.');
        }
        $pauta->loadMissing('material.campania.proforma');
        $this->validarDentroDeCampania($nuevoEspacio, $pauta->material->campania);

        return DB::transaction(function () use ($pauta, $nuevoEspacio, $actor) {
            // Liberar espacio anterior
            $pauta->espacio()->update(['estado' => EspacioPublicitario::ESTADO_DISPONIBLE]);

            // Ocupar nuevo espacio
            $nuevoEspacio->update(['estado' => EspacioPublicitario::ESTADO_OCUPADO]);

            $pauta->update(['id_espacio' => $nuevoEspacio->id_espacio]);
            $this->avisarCliente($pauta, "Pauta reprogramada: {$pauta->material->nombre_archivo}",
                "La emisión de «{$pauta->material->nombre_archivo}» se cambió a {$this->cuando($nuevoEspacio)}.", $actor);

            return $pauta;
        });
    }

    /** Registrar emisión o publicación en vivo con URL del contenido. */
    public function emitir(ProgramacionPauta $pauta, ?string $urlPublicacion, Usuario $actor): ProgramacionPauta
    {
        if (! $pauta->estaProgramada()) {
            throw new NegocioException("La pauta {$pauta->id_programacion} ya se encuentra «{$pauta->estado}».");
        }

        $pauta->loadMissing(['espacio.canal', 'material.campania.proforma']);
        if ($pauta->material->estado !== MaterialPublicitario::ESTADO_APROBADO) {
            throw new NegocioException("El material «{$pauta->material->nombre_archivo}» está «{$pauta->material->estado}»; solo se emite material aprobado por Canal 11.");
        }
        if ($pauta->espacio->fecha->toDateString() > now()->toDateString()) {
            throw new NegocioException('La pauta está programada para el '.$pauta->espacio->fecha->format('d/m/Y').'; se marca como emitida recién ese día.');
        }

        $pauta->update([
            'estado' => ProgramacionPauta::ESTADO_EMITIDA,
            'url_publicacion' => $urlPublicacion ?: null,
        ]);

        $enlace = $urlPublicacion ? " Puede verla en: {$urlPublicacion}" : '';
        $this->avisarCliente($pauta, "Su anuncio se emitió: {$pauta->material->nombre_archivo}",
            "«{$pauta->material->nombre_archivo}» salió al aire en {$this->cuando($pauta->espacio)}.".$enlace.' Las métricas de alcance aparecerán en «Métricas» cuando se registren.', $actor);
        $this->avisos->alPersonal('metricas.crear', "Registrar métricas de la pauta #{$pauta->id_programacion}",
            "La pauta «{$pauta->material->nombre_archivo}» ({$pauta->espacio->canal->nombre}) ya se emitió. Registre su alcance, impresiones e interacciones en «Métricas».",
            $actor, $pauta->id_programacion);

        return $pauta;
    }

    /** Cancelar pauta y liberar el espacio publicitario. */
    public function cancelar(ProgramacionPauta $pauta, Usuario $actor): ProgramacionPauta
    {
        if (! $pauta->estaProgramada()) {
            throw new NegocioException("La pauta {$pauta->id_programacion} está «{$pauta->estado}» y no se puede cancelar.");
        }

        return DB::transaction(function () use ($pauta, $actor) {
            // Liberar espacio
            $pauta->espacio()->update(['estado' => EspacioPublicitario::ESTADO_DISPONIBLE]);

            // Actualizar estado
            $pauta->update(['estado' => ProgramacionPauta::ESTADO_CANCELADA]);

            $pauta->loadMissing(['espacio.canal', 'material.campania.proforma']);
            $this->avisarCliente($pauta, "Pauta cancelada: {$pauta->material->nombre_archivo}",
                "Se canceló la emisión de «{$pauta->material->nombre_archivo}» prevista para {$this->cuando($pauta->espacio)}. Canal 11 la reprogramará en otro espacio.", $actor);

            return $pauta;
        });
    }

    /** El espacio debe caer dentro de las fechas de la campaña (si las tiene). */
    private function validarDentroDeCampania(EspacioPublicitario $espacio, \App\Models\Campania $campania): void
    {
        $dia = $espacio->fecha->toDateString();
        if (($campania->fecha_inicio && $dia < $campania->fecha_inicio->toDateString())
            || ($campania->fecha_fin && $dia > $campania->fecha_fin->toDateString())) {
            $rango = ($campania->fecha_inicio?->format('d/m/Y') ?? '…').' al '.($campania->fecha_fin?->format('d/m/Y') ?? '…');
            throw new NegocioException("El espacio es del {$espacio->fecha->format('d/m/Y')}, fuera de las fechas de la campaña «{$campania->nombre}» ({$rango}).");
        }
    }

    private function cuando(EspacioPublicitario $e): string
    {
        $e->loadMissing('canal');

        return ($e->canal?->nombre ?? 'el canal').' el '.$e->fecha->format('d/m/Y').' de '.substr((string) $e->hora_inicio, 0, 5).' a '.substr((string) $e->hora_fin, 0, 5);
    }

    /** Aviso al cliente: representantes de la empresa y quien subió el material. */
    private function avisarCliente(ProgramacionPauta $pauta, string $titulo, string $mensaje, Usuario $actor): void
    {
        $pauta->loadMissing('material.campania.proforma');
        $destinos = array_merge($this->avisos->cliente($pauta->material->campania->proforma), [(int) $pauta->material->id_usuario]);
        $this->avisos->enviar($destinos, $titulo, $mensaje, $pauta->id_programacion, $actor);
    }
}
