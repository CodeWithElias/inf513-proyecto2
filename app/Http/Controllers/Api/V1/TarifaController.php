<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\TarifaRequest;
use App\Models\Tarifa;
use App\Services\TarifaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TarifaController extends Controller
{
    public function __construct(private readonly TarifaService $tarifas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->tarifas->listar($request->query(), $this->porPagina($request)));
    }

    public function show(Tarifa $tarifa): JsonResponse
    {
        return $this->ok($tarifa->load(['servicio:id_servicio,nombre,tipo', 'unidad:id_unidad,nombre']));
    }

    public function store(TarifaRequest $request): JsonResponse
    {
        return $this->creado($this->tarifas->crear($request->validated()), 'Tarifa registrada correctamente.');
    }

    public function update(TarifaRequest $request, Tarifa $tarifa): JsonResponse
    {
        return $this->ok($this->tarifas->actualizar($tarifa, $request->validated()), 'Tarifa actualizada correctamente.');
    }

    public function estado(EstadoRequest $request, Tarifa $tarifa): JsonResponse
    {
        $activo = $request->boolean('activo');
        $t = $this->tarifas->cambiarEstado($tarifa, $activo);

        return $this->ok($t, $activo ? 'Tarifa reactivada.' : 'Tarifa dada de baja.');
    }
}

