<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\ServicioRequest;
use App\Models\ServicioPublicitario;
use App\Services\ServicioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    public function __construct(private readonly ServicioService $servicios)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->servicios->listar($request->query(), $this->porPagina($request)));
    }

    public function show(ServicioPublicitario $servicio): JsonResponse
    {
        return $this->ok($servicio->load('tarifas'));
    }

    public function store(ServicioRequest $request): JsonResponse
    {
        return $this->creado($this->servicios->crear($request->validated()), 'Servicio publicitario registrado correctamente.');
    }

    public function update(ServicioRequest $request, ServicioPublicitario $servicio): JsonResponse
    {
        return $this->ok($this->servicios->actualizar($servicio, $request->validated()), 'Servicio publicitario actualizado correctamente.');
    }

    public function estado(EstadoRequest $request, ServicioPublicitario $servicio): JsonResponse
    {
        $activo = $request->boolean('activo');
        $s = $this->servicios->cambiarEstado($servicio, $activo);

        return $this->ok($s, $activo ? 'Servicio reactivado.' : 'Servicio dado de baja.');
    }
}

