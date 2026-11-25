<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /opciones/{tipo} -> [{ id, nombre, ... }] para llenar los <select> de los formularios.
 * Solo devuelve registros activos e información mínima.
 */
class OpcionesController extends Controller
{
    public function show(Request $request, string $tipo): JsonResponse
    {
        $actor = $request->user();

        $q = match ($tipo) {
            'roles' => DB::table('rol')->where('activo', true)->select('id_rol as id', 'nombre'),
            'unidades' => DB::table('unidad_universitaria')->where('activo', true)->select('id_unidad as id', 'nombre'),
            'empresas' => DB::table('empresa_anunciante')->where('activo', true)
                ->when($actor?->esCliente(), fn ($b) => $b->where('id_empresa', $actor->idEmpresa() ?? 0))
                ->select('id_empresa as id', 'razon_social as nombre'),
            'plataformas' => DB::table('plataforma')->select('id_plataforma as id', 'nombre'),
            'usuarios' => DB::table('usuario')->where('activo', true)
                ->selectRaw("id_usuario as id, nombre_completo || ' (' || email || ')' as nombre"),
            'usuarios-sin-cliente' => DB::table('usuario as u')->where('u.activo', true)
                ->whereNotExists(fn ($w) => $w->from('representante as r')->whereColumn('r.id_usuario', 'u.id_usuario'))
                ->selectRaw("u.id_usuario as id, u.nombre_completo || ' (' || u.email || ')' as nombre"),

            // Persona B - Comercial y Pagos
            'servicios' => DB::table('servicio_publicitario')->where('activo', true)
                ->select('id_servicio as id', 'nombre'),
            'tarifas' => DB::table('tarifa as t')
                ->join('servicio_publicitario as s', 's.id_servicio', '=', 't.id_servicio')
                ->where('t.activo', true)
                ->where('s.activo', true)
                ->selectRaw("t.id_tarifa as id, s.nombre || ' · Bs ' || to_char(t.monto, 'FM999999990.00') || COALESCE(' (' || t.unidad_cobro || ')', '') as nombre, t.monto"),
            'paquetes' => DB::table('paquete')->where('activo', true)
                ->selectRaw("id_paquete as id, nombre || ' · Bs ' || to_char(precio_total, 'FM999999990.00') as nombre, precio_total as monto"),
            'visitantes' => DB::table('visitante')
                ->selectRaw("id_visitante as id, nombre || COALESCE(' (' || email || ')', '') as nombre"),
            'proformas-sin-campania' => DB::table('proforma as p')
                ->leftJoin('empresa_anunciante as e', 'e.id_empresa', '=', 'p.id_empresa')
                ->where('p.estado', 'aprobada')
                ->whereNotExists(fn ($w) => $w->from('campania as c')->whereColumn('c.id_proforma', 'p.id_proforma'))
                ->when($actor?->esCliente(), fn ($b) => $b->where('p.id_empresa', $actor->idEmpresa() ?? 0))
                ->selectRaw("p.id_proforma as id, p.nro_proforma || COALESCE(' · ' || e.razon_social, '') || ' (Bs ' || to_char(p.total, 'FM999999990.00') || ')' as nombre"),
            'proformas-pagables' => DB::table('proforma as p')
                ->leftJoin('empresa_anunciante as e', 'e.id_empresa', '=', 'p.id_empresa')
                ->where('p.estado', 'aprobada')
                ->where('p.total', '>', 0)
                ->whereNotExists(fn ($w) => $w->from('transaccion_pago as tp')
                    ->whereColumn('tp.id_proforma', 'p.id_proforma')
                    ->whereNull('tp.id_cuota')
                    ->where('tp.estado', 'aprobada'))
                ->whereNotExists(fn ($w) => $w->from('plan_pago as pl')
                    ->whereColumn('pl.id_proforma', 'p.id_proforma')
                    ->whereIn('pl.estado', ['activo', 'completado']))
                ->when($actor?->esCliente(), fn ($b) => $b->where('p.id_empresa', $actor->idEmpresa() ?? 0))
                ->selectRaw("p.id_proforma as id, p.nro_proforma || COALESCE(' · ' || e.razon_social, '') || ' (Bs ' || to_char(p.total, 'FM999999990.00') || ')' as nombre, p.total as monto"),
            'metodos-pago' => DB::table('metodo_pago')
                ->where('activo', true)
                ->when($actor && ! $actor->esPersonal(), fn ($b) => $b->where('id_usuario', $actor->id_usuario))
                ->selectRaw("id_metodo as id, COALESCE(marca, tipo) || COALESCE(' •••• ' || ultimos4, '') || ' · ' || titular as nombre, tipo, ultimos4, predeterminado"),

            // Persona C - Operación, Espacios y Pautas
            'campanias' => DB::table('campania as c')
                ->join('proforma as p', 'p.id_proforma', '=', 'c.id_proforma')
                ->leftJoin('empresa_anunciante as e', 'e.id_empresa', '=', 'p.id_empresa')
                ->whereIn('c.estado', ['activa', 'pausada'])
                ->when($actor?->esCliente(), fn ($b) => $b->where('p.id_empresa', $actor->idEmpresa() ?? 0))
                ->selectRaw("c.id_campania as id, c.nombre || COALESCE(' · ' || e.razon_social, '') || ' (' || c.estado || ')' as nombre"),
            'canales' => DB::table('canal_difusion')
                ->where('activo', true)
                ->selectRaw("id_canal as id, nombre || ' (' || tipo || ')' as nombre, tipo"),
            'materiales-aprobados' => DB::table('material_publicitario as m')
                ->join('campania as c', 'c.id_campania', '=', 'm.id_campania')
                ->join('proforma as p', 'p.id_proforma', '=', 'c.id_proforma')
                ->where('m.estado', 'aprobado')
                ->when($actor?->esCliente(), fn ($b) => $b->where('p.id_empresa', $actor->idEmpresa() ?? 0))
                ->selectRaw("m.id_material as id, m.nombre_archivo || ' (' || m.tipo || ' · v' || m.version || ') · ' || c.nombre as nombre, m.id_campania"),
            'espacios-disponibles' => DB::table('espacio_publicitario as e')
                ->join('canal_difusion as c', 'c.id_canal', '=', 'e.id_canal')
                ->where('e.estado', 'disponible')
                ->where('e.fecha', '>=', now()->toDateString())
                ->selectRaw("e.id_espacio as id, c.nombre || ' · ' || to_char(e.fecha, 'DD/MM/YYYY') || ' (' || to_char(e.hora_inicio, 'HH24:MI') || ' - ' || to_char(e.hora_fin, 'HH24:MI') || ')' as nombre"),
            'detalles-proforma' => DB::table('detalle_proforma as dp')
                ->join('proforma as p', 'p.id_proforma', '=', 'dp.id_proforma')
                ->join('campania as c', 'c.id_proforma', '=', 'p.id_proforma')
                ->leftJoin('tarifa as t', 't.id_tarifa', '=', 'dp.id_tarifa')
                ->leftJoin('servicio_publicitario as s', 's.id_servicio', '=', 't.id_servicio')
                ->leftJoin('paquete as pa', 'pa.id_paquete', '=', 'dp.id_paquete')
                ->when($actor?->esCliente(), fn ($b) => $b->where('p.id_empresa', $actor->idEmpresa() ?? 0))
                ->selectRaw("dp.id_detalle as id, COALESCE(s.nombre, pa.nombre, 'Servicio/Paquete') || ' (Cant: ' || dp.cantidad || ')' as nombre, c.id_campania"),
            'pautas-emitidas' => DB::table('programacion_pauta as pp')
                ->join('material_publicitario as m', 'm.id_material', '=', 'pp.id_material')
                ->join('campania as c', 'c.id_campania', '=', 'm.id_campania')
                ->join('espacio_publicitario as e', 'e.id_espacio', '=', 'pp.id_espacio')
                ->join('canal_difusion as cd', 'cd.id_canal', '=', 'e.id_canal')
                ->whereIn('pp.estado', ['emitida', 'programada'])
                ->selectRaw("pp.id_programacion as id, '#' || pp.id_programacion || ' · ' || m.nombre_archivo || ' (' || cd.nombre || ' · ' || to_char(e.fecha, 'DD/MM/YYYY') || ')' as nombre"),

            default => throw new NegocioException("No existe la lista de opciones «{$tipo}».", 404),
        };

        return $this->ok($q->orderBy('nombre')->get());
    }
}
