<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\MaterialPublicitario;
use App\Models\Proforma;
use App\Models\ProgramacionPauta;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de contratación de principio a fin, para la página de Inicio:
 *   1. Proforma aprobada → 2. Pagada → 3. Material cargado → 4. Material aprobado → 5. Pautas programadas → 6. Emitidas
 *
 * - contrataciones(): las proformas del cliente con el paso en que está cada una y qué le toca hacer.
 * - pendientes(): lo que espera acción del personal, según sus privilegios (nada queda olvidado).
 */
class FlujoService
{
    public function __construct(
        private readonly CampaniaService $campanias,
        private readonly ContratacionService $contratacion,
    ) {
    }

    public function resumen(Usuario $u): array
    {
        $this->campanias->finalizarVencidas();

        return [
            'contrataciones' => $u->esCliente() ? $this->contrataciones($u) : [],
            'pendientes' => $u->esCliente() ? [] : $this->pendientes($u),
        ];
    }

    /** Proformas vigentes del cliente con su avance. */
    public function contrataciones(Usuario $u): array
    {
        $proformas = Proforma::query()
            ->with(['campania', 'empresa:id_empresa,razon_social'])
            ->where(fn ($q) => $u->filtrarProformas($q))
            ->whereIn('estado', ['pendiente', 'aprobada'])
            ->orderByDesc('id_proforma')
            ->limit(8)
            ->get();

        return $proformas->map(fn (Proforma $p) => $this->avance($p, $u))->all();
    }

    private function avance(Proforma $p, Usuario $u): array
    {
        $campania = $p->campania;
        $mat = $campania ? MaterialPublicitario::where('id_campania', $campania->id_campania)
            ->selectRaw("count(*) filter (where estado <> 'retirado') as total,
                         count(*) filter (where estado = 'pendiente') as pendientes,
                         count(*) filter (where estado = 'correccion') as correccion,
                         count(*) filter (where estado = 'aprobado') as aprobados")->first() : null;
        $pautas = $campania ? DB::table('programacion_pauta as pp')
            ->join('material_publicitario as m', 'm.id_material', '=', 'pp.id_material')
            ->where('m.id_campania', $campania->id_campania)
            ->selectRaw('count(*) filter (where pp.estado = ?) as programadas, count(*) filter (where pp.estado = ?) as emitidas',
                [ProgramacionPauta::ESTADO_PROGRAMADA, ProgramacionPauta::ESTADO_EMITIDA])->first() : null;
        $programadas = (int) ($pautas->programadas ?? 0);
        $emitidas = (int) ($pautas->emitidas ?? 0);
        $materialOk = ($mat->aprobados ?? 0) > 0 && ($mat->pendientes ?? 0) + ($mat->correccion ?? 0) === 0;
        $ruta = "/contratar/{$p->id_proforma}";

        if ($p->estado === 'pendiente') {
            // Contratación en curso: los 4 pasos obligatorios para aprobar la proforma
            $c = $this->contratacion->estado($p, $u);
            $hechos = array_column($c['pasos'], 'hecho', 'clave');
            $pendiente = collect($c['pasos'])->firstWhere('hecho', false);
            $nro = array_search($pendiente['clave'] ?? 'pago', array_column($c['pasos'], 'clave'), true) + 1;
            $pasos = [
                ['clave' => 'campania', 'titulo' => 'Datos de la campaña', 'hecho' => $hechos['campania']],
                ['clave' => 'material', 'titulo' => 'Material de cada servicio', 'hecho' => $hechos['material']],
                ['clave' => 'agenda', 'titulo' => 'Días y horarios', 'hecho' => $hechos['agenda']],
                ['clave' => 'pago', 'titulo' => 'Pago y aprobación', 'hecho' => false],
                ['clave' => 'revision', 'titulo' => 'Revisión de Canal 11', 'hecho' => false],
                ['clave' => 'emision', 'titulo' => 'Anuncio emitido', 'hecho' => false],
            ];
            $siguiente = $c['requiere_empresa']
                ? 'Registre su empresa en «Mi perfil» para poder contratar.'
                : "Complete la contratación: paso {$nro} de 4 — {$pendiente['titulo']}. Hasta terminarla, la proforma sigue pendiente.";

            return $this->fila($p, $campania, $pasos, $siguiente, $c['requiere_empresa'] ? '/perfil' : $ruta, true);
        }

        // Aprobada: la contratación terminó (o es una proforma anterior a la contratación guiada)
        $pago = $p->situacionPago();
        $pagada = (bool) $pago['habilita_pautas'];
        $campaniaAbierta = $campania && in_array($campania->estado, ['activa', 'pausada'], true);
        $pasos = [
            ['clave' => 'campania', 'titulo' => 'Datos de la campaña', 'hecho' => $campania !== null],
            ['clave' => 'material', 'titulo' => 'Material de cada servicio', 'hecho' => ($mat->total ?? 0) > 0],
            ['clave' => 'agenda', 'titulo' => 'Días y horarios', 'hecho' => $programadas + $emitidas > 0],
            ['clave' => 'pago', 'titulo' => 'Pago y aprobación', 'hecho' => $pagada, 'detalle' => $pago['detalle']],
            ['clave' => 'revision', 'titulo' => 'Revisión de Canal 11', 'hecho' => $materialOk],
            ['clave' => 'emision', 'titulo' => 'Anuncio emitido', 'hecho' => $emitidas > 0],
        ];

        [$siguiente, $ruta, $leToca] = match (true) {
            $campania && ! $campaniaAbierta => ["La campaña está {$campania->estado}.", '/campanias', false],
            ! $pagada => [($pago['estado'] ?? '') === Proforma::PAGO_ATRASADO ? 'Tiene cuotas vencidas: póngase al día en «Planes de pago».' : 'Pague la proforma al contado o en cuotas.', ($pago['estado'] ?? '') === Proforma::PAGO_PENDIENTE ? '/pagos' : '/planes-pago', true],
            ($mat->correccion ?? 0) > 0 => ['Canal 11 pidió correcciones: suba la nueva versión del material.', '/materiales?estado=correccion', true],
            ($mat->pendientes ?? 0) > 0 => ['Canal 11 está revisando su material; sus emisiones salen cuando lo apruebe.', '/materiales', false],
            $programadas + $emitidas === 0 => ['Canal 11 programará sus pautas en los espacios contratados.', '/pautas', false],
            $emitidas === 0 => ["Tiene {$programadas} emisión(es) programada(s); saldrán al aire en las fechas elegidas.", '/pautas', false],
            default => ["Su anuncio ya salió al aire ({$emitidas} emisión(es)). Vea su impacto en «Métricas».", '/metricas', false],
        };

        return $this->fila($p, $campania, $pasos, $siguiente, $ruta, $leToca);
    }

    private function fila(Proforma $p, ?Campania $campania, array $pasos, string $siguiente, string $ruta, bool $leToca): array
    {
        return [
            'id_proforma' => $p->id_proforma,
            'nro_proforma' => $p->nro_proforma,
            'total' => $p->total,
            'estado' => $p->estado,
            'campania' => $campania ? ['id_campania' => $campania->id_campania, 'nombre' => $campania->nombre, 'estado' => $campania->estado] : null,
            'pasos' => $pasos,
            'siguiente' => $siguiente,
            'ruta' => $ruta,
            'le_toca' => $leToca,
        ];
    }

    /** Tareas pendientes del personal, solo las que su rol puede atender. */
    public function pendientes(Usuario $u): array
    {
        $hoy = now()->toDateString();
        $lista = [];
        $agregar = function (string $privilegio, string $titulo, string $detalle, string $ruta, int $cantidad, string $tono = 'aviso') use (&$lista, $u) {
            if ($cantidad > 0 && $u->tienePrivilegio($privilegio)) {
                $lista[] = compact('titulo', 'detalle', 'ruta', 'cantidad', 'tono');
            }
        };

        $agregar('solicitudes.editar', 'Solicitudes de cuenta', 'Visitantes que piden una cuenta de cliente.', '/solicitudes',
            DB::table('solicitud_cuenta')->where('estado', 'pendiente')->count());

        $agregar('proformas.aprobar', 'Proformas pendientes', 'El cliente aún no termina la contratación (o puede completarla en su nombre o rechazarla).', '/proformas?estado=pendiente',
            Proforma::where('estado', 'pendiente')->where(fn ($q) => $q->whereNull('fecha_validez')->orWhere('fecha_validez', '>=', $hoy))->count());

        $agregar('revisiones.crear', 'Material por revisar', 'Piezas cargadas que esperan revisión.', '/materiales?estado=pendiente',
            MaterialPublicitario::where('estado', MaterialPublicitario::ESTADO_PENDIENTE)->count());

        // Aprobadas sin pago: cobranza
        $aprobadas = Proforma::where('estado', 'aprobada')->with('planesPago.cuotas')->get();
        $sinPagar = $aprobadas->filter(fn (Proforma $p) => ! $p->situacionPago()['habilita_pautas']);
        $agregar('pagos.ver', 'Proformas aprobadas sin pagar', 'Falta el pago (o hay cuotas vencidas): las pautas no se pueden programar.', '/proformas?estado=aprobada',
            $sinPagar->count(), 'neutro');

        // Listas para programar: pagadas, campaña abierta, con material aprobado y sin pautas pendientes ni emitidas
        $listas = Campania::query()
            ->whereIn('estado', ['activa', 'pausada'])
            ->whereIn('id_proforma', $aprobadas->diff($sinPagar)->pluck('id_proforma'))
            ->whereHas('materiales', fn ($q) => $q->where('estado', MaterialPublicitario::ESTADO_APROBADO))
            ->whereDoesntHave('materiales.pautas', fn ($q) => $q->whereIn('estado', [ProgramacionPauta::ESTADO_PROGRAMADA, ProgramacionPauta::ESTADO_EMITIDA]))
            ->count();
        $agregar('pautas.crear', 'Campañas listas para programar', 'Pagadas y con material aprobado, todavía sin pautas.', '/pautas', $listas, 'exito');

        $porEmitir = DB::table('programacion_pauta as pp')
            ->join('espacio_publicitario as e', 'e.id_espacio', '=', 'pp.id_espacio')
            ->where('pp.estado', ProgramacionPauta::ESTADO_PROGRAMADA)
            ->where('e.fecha', '<=', $hoy)
            ->count();
        $agregar('pautas.editar', 'Pautas por confirmar emisión', 'Su fecha ya llegó: márquelas como emitidas (o cancélelas).', '/pautas?estado=programada', $porEmitir, 'peligro');

        $sinMetricas = DB::table('programacion_pauta as pp')
            ->where('pp.estado', ProgramacionPauta::ESTADO_EMITIDA)
            ->whereNotExists(fn ($q) => $q->from('metrica_publicacion as mp')->whereColumn('mp.id_programacion', 'pp.id_programacion'))
            ->count();
        $agregar('metricas.crear', 'Pautas emitidas sin métricas', 'Registre su alcance e interacciones para el reporte del cliente.', '/metricas', $sinMetricas, 'neutro');

        $cuotasVencidas = DB::table('cuota as c')
            ->join('plan_pago as pl', 'pl.id_plan', '=', 'c.id_plan')
            ->where('pl.estado', 'activo')
            ->where('c.estado', '<>', 'pagada')
            ->where('c.fecha_vencimiento', '<', $hoy)
            ->count();
        $agregar('planes_pago.ver', 'Cuotas vencidas', 'Cuotas de planes activos que pasaron su fecha sin pagarse.', '/planes-pago', $cuotasVencidas, 'peligro');

        $paginas = DB::table('pagina_no_autorizada')->where('estado', 'detectada')->count();
        $agregar('paginas.editar', 'Páginas no autorizadas por revisar', 'Detectadas por el monitoreo: confírmelas, denúncielas o descártelas.', '/paginas', $paginas, 'neutro');

        return $lista;
    }
}
