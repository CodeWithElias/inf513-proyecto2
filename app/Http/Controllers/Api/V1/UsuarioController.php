<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\UsuarioRequest;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->usuarios->listar($request->query(), $this->porPagina($request)));
    }

    public function show(Usuario $usuario): JsonResponse
    {
        return $this->ok($usuario->load(['rol:id_rol,nombre', 'unidad:id_unidad,nombre', 'representante.empresa:id_empresa,razon_social']));
    }

    public function store(UsuarioRequest $request): JsonResponse
    {
        return $this->creado($this->usuarios->crear($request->validated()), 'Usuario registrado correctamente.');
    }

    public function update(UsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        return $this->ok($this->usuarios->actualizar($usuario, $request->validated(), $request->user()), 'Usuario actualizado correctamente.');
    }

    public function estado(EstadoRequest $request, Usuario $usuario): JsonResponse
    {
        $activo = $request->boolean('activo');
        $u = $this->usuarios->cambiarEstado($usuario, $activo, $request->user());

        return $this->ok($u, $activo ? 'Usuario reactivado.' : 'Usuario dado de baja.');
    }
}
