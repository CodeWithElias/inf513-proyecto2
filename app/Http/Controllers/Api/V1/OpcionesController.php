<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /opciones/{tipo} -> [{ id, nombre }] para llenar los <select> de los formularios.
 * Solo devuelve registros activos e información mínima. Persona B y C pueden
 * agregar aquí sus catálogos (servicios, tarifas, espacios...).
 */
class OpcionesController extends Controller
{
    public function show(string $tipo): JsonResponse
    {
        $q = match ($tipo) {
            'roles' => DB::table('rol')->where('activo', true)->select('id_rol as id', 'nombre'),
            'unidades' => DB::table('unidad_universitaria')->where('activo', true)->select('id_unidad as id', 'nombre'),
            'empresas' => DB::table('empresa_anunciante')->where('activo', true)->select('id_empresa as id', 'razon_social as nombre'),
            'plataformas' => DB::table('plataforma')->select('id_plataforma as id', 'nombre'),
            'usuarios' => DB::table('usuario')->where('activo', true)
                ->selectRaw("id_usuario as id, nombre_completo || ' (' || email || ')' as nombre"),
            'usuarios-sin-cliente' => DB::table('usuario as u')->where('u.activo', true)
                ->whereNotExists(fn ($w) => $w->from('representante as r')->whereColumn('r.id_usuario', 'u.id_usuario'))
                ->selectRaw("u.id_usuario as id, u.nombre_completo || ' (' || u.email || ')' as nombre"),
            default => throw new NegocioException("No existe la lista de opciones «{$tipo}».", 404),
        };

        return $this->ok($q->orderBy('nombre')->get());
    }
}
