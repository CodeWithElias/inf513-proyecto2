<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\PaqueteRequest;
use App\Models\Paquete;
use App\Services\PaqueteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaqueteController extends Controller
{
    public function __construct(private readonly PaqueteService $paquetes)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->paquetes->listar($request->query(), $this->porPagina($request)));
    }

    public function show(Paquete $paquete): JsonResponse
    {
        return $this->ok($this->paquetes->obtener($paquete));
    }

    public function store(PaqueteRequest $request): JsonResponse
    {
        return $this->creado($this->paquetes->crear($request->validated()), 'Paquete publicitario registrado correctamente.');
    }

    public function update(PaqueteRequest $request, Paquete $paquete): JsonResponse
    {
        return $this->ok($this->paquetes->actualizar($paquete, $request->validated()), 'Paquete publicitario actualizado correctamente.');
    }

    public function estado(EstadoRequest $request, Paquete $paquete): JsonResponse
    {
        $activo = $request->boolean('activo');
        $p = $this->paquetes->cambiarEstado($paquete, $activo);

        return $this->ok($p, $activo ? 'Paquete reactivado.' : 'Paquete dado de baja.');
    }
}

