<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Campania;
use App\Models\DetalleProforma;
use App\Models\EspacioPublicitario;
use App\Models\MaterialPublicitario;
use App\Models\Proforma;
use App\Models\ProgramacionPauta;
use App\Models\Tarifa;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contratación guiada: la única forma de aprobar una proforma.
 *   1. Campaña  → nombre y fechas (se crea la campaña «pausada» mientras se prepara)
 *   2. Material → una pieza por cada servicio contratado (del tipo que el servicio admite)
 *   3. Agenda   → tantos espacios de la agenda del canal como unidades contratadas
 *   4. Pago     → contado o cuotas; con el pago aprobado la proforma queda «aprobada»,
 *                 la campaña «activa» y los horarios se convierten en pautas programadas.
 * Mientras no se paga, la proforma sigue «pendiente» con su avance guardado.
 * Los horarios elegidos se apartan por HORAS_RESERVA (se renuevan con cada cambio).
 */
class ContratacionService
{
    public const HORAS_RESERVA = 48;

    public function __construct(
        private readonly ProformaService $proformas,
        private readonly MaterialService $materiales,
        private readonly PagoService $pagos,
        private readonly AvisoService $avisos,
    ) {
    }

    // =====================================================================================
    // Consulta del avance
    // =====================================================================================

    public function estado(Proforma $proforma, Usuario $actor): array
    {
        $this->proformas->exigirAcceso($proforma, $actor);
        $this->liberarVencidas();
        if ($proforma->estado === 'pendiente') {
            $this->prepararLineas($proforma);
        }
        $proforma->load(['empresa:id_empresa,razon_social', 'visitante:id_visitante,nombre', 'campania']);
        $campania = $proforma->campania;
        $lineas = $this->lineas($proforma);

        $conMaterial = $lineas->isNotEmpty() && $lineas->every(fn ($l) => $l['material'] !== null);
        $conAgenda = $lineas->isNotEmpty() && $lineas->every(fn ($l) => count($l['espacios']) === $l['cantidad']);
        $aprobada = $proforma->estado === 'aprobada';
        $vence = DB::table('contratacion_espacio as ce')->join('contratacion_servicio as cs', 'cs.id_linea', '=', 'ce.id_linea')
            ->where('cs.id_proforma', $proforma->id_proforma)->whereNull('ce.id_programacion')->min('ce.fecha_reserva');

        return [
            'proforma' => [
                'id_proforma' => $proforma->id_proforma, 'nro_proforma' => $proforma->nro_proforma, 'estado' => $proforma->estado,
                'total' => $proforma->total, 'fecha_validez' => $proforma->fecha_validez?->toDateString(),
                'cliente' => $proforma->empresa?->razon_social ?? $proforma->visitante?->nombre,
            ],
            'campania' => $campania ? [
                'id_campania' => $campania->id_campania, 'nombre' => $campania->nombre, 'estado' => $campania->estado,
                'fecha_inicio' => $campania->fecha_inicio?->toDateString(), 'fecha_fin' => $campania->fecha_fin?->toDateString(),
            ] : null,
            'lineas' => $lineas->values()->all(),
            'pasos' => [
                ['clave' => 'campania', 'titulo' => 'Datos de la campaña', 'hecho' => $campania !== null],
                ['clave' => 'material', 'titulo' => 'Material de cada servicio', 'hecho' => $conMaterial],
                ['clave' => 'agenda', 'titulo' => 'Días y horarios', 'hecho' => $conAgenda],
                ['clave' => 'pago', 'titulo' => 'Pago', 'hecho' => $aprobada],
            ],
            'puede_editar' => $proforma->estado === 'pendiente',
            'reservas_vencen' => $vence ? date('Y-m-d H:i:s', strtotime($vence) + self::HORAS_RESERVA * 3600) : null,
            'horas_reserva' => self::HORAS_RESERVA,
            'requiere_empresa' => $actor->esCliente() && $actor->idEmpresa() === null,
            'metodos' => $proforma->estado === 'pendiente' ? $this->metodosDisponibles($proforma, $actor) : [],
        ];
    }

    /**
     * Métodos de pago con los que se puede pagar: los del propio usuario y, si contrata el personal
     * en nombre del cliente, también los del cliente (representantes de la empresa).
     */
    private function metodosDisponibles(Proforma $proforma, Usuario $actor): array
    {
        $usuarios = $actor->esCliente() ? [$actor->id_usuario] : [...$this->avisos->cliente($proforma), $actor->id_usuario];

        return DB::table('metodo_pago as m')->join('usuario as u', 'u.id_usuario', '=', 'm.id_usuario')
            ->whereIn('m.id_usuario', $usuarios)->where('m.activo', true)->whereNotNull('m.stripe_payment_method')
            ->orderByDesc('m.predeterminado')->orderBy('m.id_metodo')
            ->get(['m.id_metodo', 'm.tipo', 'm.marca', 'm.ultimos4', 'm.titular', 'm.predeterminado', 'm.id_usuario', 'u.nombre_completo as de'])
            ->map(fn ($m) => [...(array) $m, 'propio' => (int) $m->id_usuario === (int) $actor->id_usuario])
            ->all();
    }

    // =====================================================================================
    // Paso 1: campaña
    // =====================================================================================

    public function guardarCampania(Proforma $proforma, array $d, Usuario $actor): array
    {
        $this->exigirEditable($proforma, $actor);
        $inicio = $d['fecha_inicio'];
        $fin = $d['fecha_fin'];
        if ($inicio < now()->toDateString()) {
            throw new NegocioException('La campaña no puede empezar en una fecha pasada.');
        }
        if ($fin < $inicio) {
            throw new NegocioException('La fecha de fin de la campaña no puede ser anterior a la de inicio.');
        }
        if ((strtotime($fin) - strtotime($inicio)) / 86400 > 366) {
            throw new NegocioException('Una campaña puede durar como máximo un año.');
        }

        DB::transaction(function () use ($proforma, $d, $inicio, $fin) {
            $campania = Campania::where('id_proforma', $proforma->id_proforma)->first();
            if ($campania) {
                $campania->update(['nombre' => $d['nombre'], 'fecha_inicio' => $inicio, 'fecha_fin' => $fin]);
                // Los horarios que quedaron fuera de las nuevas fechas se sueltan
                $this->reservasPendientes($proforma->id_proforma)
                    ->filter(fn ($r) => $r->fecha < $inicio || $r->fecha > $fin)
                    ->each(fn ($r) => DB::table('contratacion_espacio')->where('id_reserva', $r->id_reserva)->delete());
            } else {
                // «pausada» = en preparación: se activa al confirmar la contratación
                Campania::create(['nombre' => $d['nombre'], 'fecha_inicio' => $inicio, 'fecha_fin' => $fin,
                    'estado' => 'pausada', 'id_proforma' => $proforma->id_proforma]);
            }
        });

        return $this->estado($proforma->fresh(), $actor);
    }

    // =====================================================================================
    // Paso 2: material de cada servicio
    // =====================================================================================

    public function asignarMaterial(Proforma $proforma, int $idLinea, array $datos, Usuario $actor): array
    {
        $this->exigirEditable($proforma, $actor);
        $campania = $this->exigirCampania($proforma);
        $linea = $this->linea($proforma, $idLinea);
        $tarifa = Tarifa::with('servicio')->findOrFail($linea->id_tarifa);
        $permitidos = $this->tiposMaterial($tarifa);

        if (! empty($datos['id_material'])) {
            // Reutilizar una pieza ya cargada en esta campaña
            $material = MaterialPublicitario::where('id_campania', $campania->id_campania)->find($datos['id_material']);
            if (! $material) {
                throw new NegocioException('El material elegido no pertenece a esta campaña.');
            }
            if (in_array($material->estado, [MaterialPublicitario::ESTADO_RETIRADO, MaterialPublicitario::ESTADO_RECHAZADO], true)) {
                throw new NegocioException("El material «{$material->nombre_archivo}» está {$material->estado}; elija otro o cargue uno nuevo.");
            }
        } else {
            $tipo = $datos['tipo'] ?? '';
            if (! in_array($tipo, $permitidos, true)) {
                throw new NegocioException("«{$tarifa->servicio->nombre}» admite material de tipo: ".implode(', ', $permitidos).'.');
            }
            $this->validarDuracion($tarifa, isset($datos['duracion_seg']) ? (int) $datos['duracion_seg'] : null);
            $material = $this->materiales->crear([...$datos, 'id_campania' => $campania->id_campania], $actor);
        }

        if (! in_array($material->tipo, $permitidos, true)) {
            throw new NegocioException("«{$tarifa->servicio->nombre}» admite material de tipo: ".implode(', ', $permitidos).". «{$material->nombre_archivo}» es {$material->tipo}.");
        }
        $this->validarDuracion($tarifa, $material->duracion_seg);
        DB::table('contratacion_servicio')->where('id_linea', $linea->id_linea)->update(['id_material' => $material->id_material]);

        return $this->estado($proforma->fresh(), $actor);
    }

    // =====================================================================================
    // Paso 3: agenda del canal
    // =====================================================================================

    /** Espacios que se pueden elegir para un servicio (los ya elegidos vienen marcados). */
    public function espaciosDisponibles(Proforma $proforma, int $idLinea, Usuario $actor): array
    {
        $this->proformas->exigirAcceso($proforma, $actor);
        $this->liberarVencidas();
        $campania = $this->exigirCampania($proforma);
        $linea = $this->linea($proforma, $idLinea);
        $tarifa = Tarifa::with('servicio')->findOrFail($linea->id_tarifa);
        $elegidos = DB::table('contratacion_espacio')->where('id_linea', $linea->id_linea)->whereNull('id_programacion')->pluck('id_espacio')->all();

        return $this->consultaDisponibles($tarifa, $campania, $linea->id_linea)
            ->orderBy('e.fecha')->orderBy('e.hora_inicio')->limit(400)
            ->get(['e.id_espacio', DB::raw('e.fecha::text as fecha'), DB::raw("to_char(e.hora_inicio,'HH24:MI') as hora_inicio"),
                DB::raw("to_char(e.hora_fin,'HH24:MI') as hora_fin"), 'c.nombre as canal'])
            ->map(fn ($e) => [...(array) $e, 'elegido' => in_array($e->id_espacio, $elegidos, true)])
            ->all();
    }

    public function reservar(Proforma $proforma, int $idLinea, array $ids, Usuario $actor): array
    {
        $this->exigirEditable($proforma, $actor);
        $campania = $this->exigirCampania($proforma);
        $linea = $this->linea($proforma, $idLinea);
        $tarifa = Tarifa::with('servicio')->findOrFail($linea->id_tarifa);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (count($ids) > $linea->cantidad) {
            throw new NegocioException("«{$tarifa->servicio->nombre}» tiene {$linea->cantidad} unidad(es) contratada(s); eligió ".count($ids).' horario(s).');
        }
        $validos = $this->consultaDisponibles($tarifa, $campania, $linea->id_linea)->whereIn('e.id_espacio', $ids)->pluck('e.id_espacio')->all();
        $invalidos = array_diff($ids, array_map('intval', $validos));
        if ($invalidos) {
            throw new NegocioException('Alguno de los horarios elegidos ya no está disponible (ID '.implode(', ', $invalidos).'). Actualice la agenda y elija otro.');
        }

        try {
            DB::transaction(function () use ($linea, $ids, $proforma) {
                DB::table('contratacion_espacio')->where('id_linea', $linea->id_linea)->whereNull('id_programacion')
                    ->whereNotIn('id_espacio', $ids ?: [0])->delete();
                $actuales = DB::table('contratacion_espacio')->where('id_linea', $linea->id_linea)->whereNull('id_programacion')->pluck('id_espacio')->all();
                foreach (array_diff($ids, $actuales) as $id) {
                    DB::table('contratacion_espacio')->insert(['id_linea' => $linea->id_linea, 'id_espacio' => $id, 'fecha_reserva' => now()]);
                }
                $this->renovarReservas($proforma->id_proforma);
            });
        } catch (QueryException) {
            throw new NegocioException('Otra persona acaba de apartar uno de esos horarios. Actualice la agenda y elija otro.');
        }

        return $this->estado($proforma->fresh(), $actor);
    }

    // =====================================================================================
    // Paso 4: pago y aprobación
    // =====================================================================================

    public function confirmar(Proforma $proforma, array $d, Usuario $actor): array
    {
        $this->exigirEditable($proforma, $actor);
        $estado = $this->estado($proforma, $actor);
        foreach (array_slice($estado['pasos'], 0, 3) as $paso) {
            if (! $paso['hecho']) {
                throw new NegocioException("Falta completar el paso «{$paso['titulo']}» antes de pagar.");
            }
        }
        $lineas = collect($estado['lineas']);
        $idsEspacios = $lineas->flatMap(fn ($l) => array_column($l['espacios'], 'id_espacio'))->all();
        $this->exigirEspaciosLibres($idsEspacios);

        if (! in_array((int) $d['id_metodo'], array_column($estado['metodos'], 'id_metodo'), true)) {
            throw new NegocioException('Elija uno de los métodos de pago ofrecidos (suyos o del cliente).');
        }
        $modalidad = $d['modalidad'] ?? 'contado';
        $monto = $modalidad === 'cuotas'
            ? $this->pagos->montoPrimeraCuota((float) $proforma->total, (int) $d['nro_cuotas'])
            : (float) $proforma->total;
        $concepto = $modalidad === 'cuotas'
            ? "contratacion:{$proforma->id_proforma}:cuotas:{$d['nro_cuotas']}:".($d['frecuencia'] ?? 'mensual')
            : "contratacion:{$proforma->id_proforma}:contado";
        $descripcion = "Contratación {$proforma->nro_proforma}".($modalidad === 'cuotas' ? " · cuota 1 de {$d['nro_cuotas']}" : ' (al contado)');
        [$metodo, $resultado] = $this->pagos->cobrar((int) $d['id_metodo'], $monto, $descripcion,
            ['concepto' => $concepto, 'id_proforma' => $proforma->id_proforma], $d['payment_intent'] ?? null, $actor);

        if ($resultado['estado'] !== 'aprobada') {
            // El intento rechazado queda registrado (trazabilidad); la proforma sigue pendiente
            $this->pagos->registrarRechazo($proforma, $metodo, $monto, $resultado, $actor);
            throw new NegocioException("El pago fue rechazado: {$resultado['mensaje']} Pruebe con otro método de pago; su avance se conserva.");
        }

        try {
            $resumen = $this->aplicarContratacion($proforma, $d, $actor, $modalidad, $metodo, $resultado, $lineas, $idsEspacios);
        } catch (\Throwable $e) {
            // Ya se cobró en Stripe pero no se pudo completar: se devuelve el dinero y no queda nada a medias
            $this->pagos->reembolsar($resultado['payment_intent'] ?? null);
            $motivo = $e instanceof NegocioException ? $e->getMessage() : 'Ocurrió un error al registrar la contratación.';
            throw new NegocioException("{$motivo} El cobro se devolvió a su tarjeta; la proforma sigue pendiente.");
        }

        $this->avisarConfirmacion($proforma->fresh(), $resumen['campania'], $resumen['pautas'], $lineas, $modalidad, $actor);

        return [...$this->estado($proforma->fresh(), $actor), 'pago' => [
            'codigo' => $resumen['tx']->codigo_autorizacion, 'monto' => $resumen['tx']->monto, 'modalidad' => $modalidad,
        ], 'pautas_programadas' => $resumen['pautas']];
    }

    /** Todo lo que pasa cuando el cobro fue aprobado, en una sola transacción. */
    private function aplicarContratacion(Proforma $proforma, array $d, Usuario $actor, string $modalidad, $metodo, array $resultado, Collection $lineas, array $idsEspacios): array
    {
        return DB::transaction(function () use ($proforma, $d, $actor, $modalidad, $metodo, $resultado, $lineas, $idsEspacios) {
            // Los espacios se bloquean y se vuelven a verificar dentro de la transacción
            DB::table('espacio_publicitario')->whereIn('id_espacio', $idsEspacios)->lockForUpdate()->get();
            $this->exigirEspaciosLibres($idsEspacios);

            $this->proformas->aprobarPorContratacion($proforma);
            $tx = $this->pagos->registrarCobroContratacion($proforma, $metodo, $resultado, $modalidad, $d, $actor);

            $campania = Campania::where('id_proforma', $proforma->id_proforma)->first();
            $campania->update(['estado' => 'activa']);

            $pautas = 0;
            foreach ($lineas as $l) {
                foreach ($l['espacios'] as $e) {
                    $pauta = ProgramacionPauta::create([
                        'id_espacio' => $e['id_espacio'], 'id_material' => $l['material']['id_material'], 'id_detalle' => $l['id_detalle'],
                        'id_usuario' => $actor->id_usuario, 'estado' => ProgramacionPauta::ESTADO_PROGRAMADA, 'fecha_programacion' => now(),
                    ]);
                    EspacioPublicitario::whereKey($e['id_espacio'])->update(['estado' => EspacioPublicitario::ESTADO_OCUPADO]);
                    DB::table('contratacion_espacio')->where('id_reserva', $e['id_reserva'])->update(['id_programacion' => $pauta->id_programacion]);
                    $pautas++;
                }
            }

            return ['campania' => $campania, 'pautas' => $pautas, 'tx' => $tx];
        });
    }

    // =====================================================================================
    // Reglas por servicio
    // =====================================================================================

    /** Tipos de material que admite el servicio de la tarifa. @return string[] */
    public function tiposMaterial(Tarifa $t): array
    {
        $tipoServicio = $t->servicio?->tipo;
        $unidad = mb_strtolower((string) $t->unidad_cobro);

        return match (true) {
            str_contains($unidad, 'menci') => ['texto'],
            $tipoServicio === 'radio' => ['audio', 'texto'],
            in_array($tipoServicio, ['television'], true) && $t->duracion_seg !== null => ['video'],
            $tipoServicio === 'television' => ['banner', 'imagen', 'texto'],
            $tipoServicio === 'web' => ['banner', 'imagen'],
            in_array($tipoServicio, ['red_social', 'redes_sociales'], true) && $t->duracion_seg !== null => ['video'],
            in_array($tipoServicio, ['red_social', 'redes_sociales'], true) => ['imagen', 'video', 'banner', 'texto'],
            default => MaterialPublicitario::TIPOS,
        };
    }

    private function validarDuracion(Tarifa $t, ?int $duracion): void
    {
        if ($t->duracion_seg && $duracion && $duracion > $t->duracion_seg) {
            throw new NegocioException("La pieza dura {$duracion} s y el servicio contratado es de {$t->duracion_seg} s. Cargue una versión de {$t->duracion_seg} s o menos.");
        }
    }

    /** Espacios libres del canal que corresponde al servicio, dentro de la campaña y desde mañana. */
    private function consultaDisponibles(Tarifa $tarifa, Campania $campania, int $idLinea)
    {
        $tipoCanal = match ($tarifa->servicio?->tipo) {
            'redes_sociales', 'red_social' => 'red_social',
            default => $tarifa->servicio?->tipo,
        };
        $desde = max(now()->addDay()->toDateString(), $campania->fecha_inicio?->toDateString() ?? '');
        // Si el servicio nombra una plataforma (ej. «Video patrocinado en YouTube»), solo sus canales
        $plataforma = DB::table('plataforma')->pluck('nombre')
            ->first(fn ($n) => mb_stripos((string) $tarifa->servicio?->nombre, $n) !== false);

        return DB::table('espacio_publicitario as e')
            ->join('canal_difusion as c', 'c.id_canal', '=', 'e.id_canal')
            ->where('c.activo', true)
            ->where('c.tipo', $tipoCanal)
            ->where('e.estado', EspacioPublicitario::ESTADO_DISPONIBLE)
            ->where('e.fecha', '>=', $desde)
            ->when($campania->fecha_fin, fn ($q) => $q->where('e.fecha', '<=', $campania->fecha_fin->toDateString()))
            ->when($plataforma, fn ($q) => $q->whereExists(fn ($w) => $w->from('red_social as r')
                ->join('plataforma as p', 'p.id_plataforma', '=', 'r.id_plataforma')
                ->whereColumn('r.id_canal', 'c.id_canal')->where('p.nombre', $plataforma)))
            // Ni apartados por otro servicio (de esta u otra contratación)
            ->whereNotExists(fn ($w) => $w->from('contratacion_espacio as ce')->whereColumn('ce.id_espacio', 'e.id_espacio')
                ->whereNull('ce.id_programacion')->where('ce.id_linea', '<>', $idLinea));
    }

    // =====================================================================================
    // Apoyo
    // =====================================================================================

    /** Crea las líneas a cumplir: cada ítem de la proforma; los paquetes se abren en sus tarifas. */
    private function prepararLineas(Proforma $proforma): void
    {
        $detalles = DetalleProforma::with('paquete.items')->where('id_proforma', $proforma->id_proforma)->get();
        foreach ($detalles as $d) {
            $partes = $d->id_tarifa
                ? [[(int) $d->id_tarifa, (int) $d->cantidad]]
                : $d->paquete->items->map(fn ($i) => [(int) $i->id_tarifa, (int) $i->cantidad * (int) $d->cantidad])->all();
            foreach ($partes as [$idTarifa, $cantidad]) {
                DB::table('contratacion_servicio')->insertOrIgnore([
                    'id_proforma' => $proforma->id_proforma, 'id_detalle' => $d->id_detalle, 'id_tarifa' => $idTarifa, 'cantidad' => $cantidad,
                ]);
            }
        }
    }

    private function lineas(Proforma $proforma): Collection
    {
        $filas = DB::table('contratacion_servicio as cs')
            ->join('tarifa as t', 't.id_tarifa', '=', 'cs.id_tarifa')
            ->join('servicio_publicitario as s', 's.id_servicio', '=', 't.id_servicio')
            ->leftJoin('detalle_proforma as d', 'd.id_detalle', '=', 'cs.id_detalle')
            ->leftJoin('paquete as pa', 'pa.id_paquete', '=', 'd.id_paquete')
            ->where('cs.id_proforma', $proforma->id_proforma)
            ->orderBy('cs.id_linea')
            ->get(['cs.*', 's.nombre as servicio', 's.tipo as tipo_servicio', 't.unidad_cobro', 't.duracion_seg', 't.monto', 'pa.nombre as paquete']);
        $materiales = MaterialPublicitario::whereIn('id_material', $filas->pluck('id_material')->filter())->get()->keyBy('id_material');
        $reservas = DB::table('contratacion_espacio as ce')
            ->join('espacio_publicitario as e', 'e.id_espacio', '=', 'ce.id_espacio')
            ->join('canal_difusion as c', 'c.id_canal', '=', 'e.id_canal')
            ->whereIn('ce.id_linea', $filas->pluck('id_linea'))
            ->where(fn ($q) => $proforma->estado === 'aprobada' ? $q->whereNotNull('ce.id_programacion') : $q->whereNull('ce.id_programacion'))
            ->orderBy('e.fecha')->orderBy('e.hora_inicio')
            ->get(['ce.id_reserva', 'ce.id_linea', 'ce.id_programacion', 'e.id_espacio', DB::raw('e.fecha::text as fecha'),
                DB::raw("to_char(e.hora_inicio,'HH24:MI') as hora_inicio"), DB::raw("to_char(e.hora_fin,'HH24:MI') as hora_fin"), 'c.nombre as canal'])
            ->groupBy('id_linea');

        return $filas->map(function ($f) use ($materiales, $reservas) {
            $tarifa = Tarifa::with('servicio')->find($f->id_tarifa);
            $m = $f->id_material ? $materiales->get($f->id_material) : null;
            // Un material retirado o rechazado ya no cumple el paso
            $valido = $m && ! in_array($m->estado, [MaterialPublicitario::ESTADO_RETIRADO, MaterialPublicitario::ESTADO_RECHAZADO], true);

            return [
                'id_linea' => $f->id_linea, 'id_detalle' => $f->id_detalle, 'id_tarifa' => $f->id_tarifa,
                'servicio' => $f->servicio, 'paquete' => $f->paquete, 'unidad_cobro' => $f->unidad_cobro,
                'duracion_seg' => $f->duracion_seg, 'cantidad' => (int) $f->cantidad,
                'tipos_material' => $this->tiposMaterial($tarifa),
                'material' => $valido ? [
                    'id_material' => $m->id_material, 'nombre_archivo' => $m->nombre_archivo, 'tipo' => $m->tipo, 'estado' => $m->estado,
                    'duracion_seg' => $m->duracion_seg, 'url_vista' => $m->url_vista, 'texto_publicacion' => $m->texto_publicacion,
                    'archivo_interno' => $m->archivo_interno, 'version' => $m->version, 'formato' => $m->formato, 'tamano_bytes' => $m->tamano_bytes,
                ] : null,
                'espacios' => ($reservas->get($f->id_linea) ?? collect())->map(fn ($r) => (array) $r)->values()->all(),
            ];
        });
    }

    private function linea(Proforma $proforma, int $idLinea): object
    {
        $linea = DB::table('contratacion_servicio')->where('id_proforma', $proforma->id_proforma)->where('id_linea', $idLinea)->first();
        if (! $linea) {
            throw new NegocioException('Ese servicio no pertenece a esta proforma. Recargue la página.');
        }

        return $linea;
    }

    private function exigirEditable(Proforma $proforma, Usuario $actor): void
    {
        $this->proformas->obtener($proforma, $actor); // acceso + marca vencida si corresponde
        $proforma->refresh();
        if ($proforma->estado !== 'pendiente') {
            throw new NegocioException("La proforma {$proforma->nro_proforma} está «{$proforma->estado}»; la contratación solo se hace sobre proformas pendientes.");
        }
        if ($actor->esCliente() && $actor->idEmpresa() === null) {
            throw new NegocioException('Para contratar primero registre su empresa en «Mi perfil»: sus proformas de visitante pasarán a la empresa.', 403);
        }
        $this->liberarVencidas();
        $this->prepararLineas($proforma);
    }

    private function exigirCampania(Proforma $proforma): Campania
    {
        $campania = Campania::where('id_proforma', $proforma->id_proforma)->first();
        if (! $campania) {
            throw new NegocioException('Primero complete los datos de la campaña (paso 1).');
        }

        return $campania;
    }

    private function exigirEspaciosLibres(array $ids): void
    {
        $ocupados = EspacioPublicitario::whereIn('id_espacio', $ids)->where('estado', '<>', EspacioPublicitario::ESTADO_DISPONIBLE)->pluck('id_espacio')->all();
        if ($ocupados) {
            throw new NegocioException('Mientras completaba la contratación se ocuparon algunos horarios (espacio '.implode(', ', $ocupados).'). Vuelva al paso 3 y elija otros.');
        }
    }

    private function reservasPendientes(int $idProforma): Collection
    {
        return DB::table('contratacion_espacio as ce')
            ->join('contratacion_servicio as cs', 'cs.id_linea', '=', 'ce.id_linea')
            ->join('espacio_publicitario as e', 'e.id_espacio', '=', 'ce.id_espacio')
            ->where('cs.id_proforma', $idProforma)->whereNull('ce.id_programacion')
            ->get(['ce.id_reserva', DB::raw('e.fecha::text as fecha')]);
    }

    private function renovarReservas(int $idProforma): void
    {
        DB::table('contratacion_espacio')->whereNull('id_programacion')
            ->whereIn('id_linea', DB::table('contratacion_servicio')->where('id_proforma', $idProforma)->select('id_linea'))
            ->update(['fecha_reserva' => now()]);
    }

    /** Suelta los horarios apartados que no se tocaron en HORAS_RESERVA horas (contrataciones abandonadas). */
    public function liberarVencidas(): int
    {
        return DB::table('contratacion_espacio')->whereNull('id_programacion')
            ->where('fecha_reserva', '<', now()->subHours(self::HORAS_RESERVA))->delete();
    }

    private function avisarConfirmacion(Proforma $p, Campania $c, int $pautas, Collection $lineas, string $modalidad, Usuario $actor): void
    {
        $forma = $modalidad === 'cuotas' ? 'con la primera cuota pagada' : 'pagada al contado';
        $piezas = $lineas->pluck('material.id_material')->unique()->count();
        $this->avisos->alCliente($p, "Contratación confirmada: {$p->nro_proforma}",
            "Su proforma quedó aprobada ({$forma}). Se programaron {$pautas} emisión(es) en la campaña «{$c->nombre}». Canal 11 revisará sus {$piezas} pieza(s) de material antes de emitirlas; si pide correcciones se le avisará.", $actor);
        $this->avisos->alPersonal('revisiones.crear', "Material por revisar · {$p->nro_proforma}",
            "Se confirmó la contratación de «{$c->nombre}» ({$forma}): {$piezas} pieza(s) de material por revisar y {$pautas} pauta(s) programada(s). Las pautas se emiten cuando su material esté aprobado.", $actor);
    }
}
