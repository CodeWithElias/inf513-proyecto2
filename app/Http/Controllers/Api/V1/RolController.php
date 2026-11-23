<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\PrivilegiosRolRequest;
use App\Http\Requests\RolRequest;
use App\Models\Rol;
use App\Services\RolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function __construct(private readonly RolService $roles)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->roles->listar($request->query('q')));
    }

    public function show(Rol $rol): JsonResponse
    {
        return $this->ok($rol);
    }

    public function store(RolRequest $request): JsonResponse
    {
        return $this->creado($this->roles->crear($request->validated()), 'Rol registrado correctamente.');
    }

    public function update(RolRequest $request, Rol $rol): JsonResponse
    {
        return $this->ok($this->roles->actualizar($rol, $request->validated()), 'Rol actualizado correctamente.');
    }

    public function estado(EstadoRequest $request, Rol $rol): JsonResponse
    {
        $activo = $request->boolean('activo');

        return $this->ok($this->roles->cambiarEstado($rol, $activo), $activo ? 'Rol reactivado.' : 'Rol dado de baja.');
    }

    /** GET /roles/{rol}/privilegios -> matriz agrupada por módulo */
    public function privilegios(Rol $rol): JsonResponse
    {
        return $this->ok(['rol' => $rol, 'modulos' => $this->roles->matriz($rol)]);
    }

    /** PUT /roles/{rol}/privilegios { privilegios: [id, ...] } */
    public function asignar(PrivilegiosRolRequest $request, Rol $rol): JsonResponse
    {
        $this->roles->asignarPrivilegios($rol, $request->validated('privilegios'));

        return $this->ok(['rol' => $rol, 'modulos' => $this->roles->matriz($rol)],
            "Permisos del rol {$rol->nombre} actualizados. Los usuarios verán los cambios al recargar la página.");
    }
}
