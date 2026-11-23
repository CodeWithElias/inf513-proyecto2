<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\UnidadRequest;
use App\Models\UnidadUniversitaria;
use App\Services\UnidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadController extends Controller
{
    public function __construct(private readonly UnidadService $unidades)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->unidades->listar($request->query(), $this->porPagina($request)));
    }

    public function show(UnidadUniversitaria $unidad): JsonResponse
    {
        return $this->ok($unidad->load(['padre:id_unidad,nombre', 'hijas:id_unidad,nombre,activo,id_unidad_padre']));
    }

    public function store(UnidadRequest $request): JsonResponse
    {
        return $this->creado($this->unidades->crear($request->validated()), 'Unidad registrada correctamente.');
    }

    public function update(UnidadRequest $request, UnidadUniversitaria $unidad): JsonResponse
    {
        return $this->ok($this->unidades->actualizar($unidad, $request->validated()), 'Unidad actualizada correctamente.');
    }

    public function estado(EstadoRequest $request, UnidadUniversitaria $unidad): JsonResponse
    {
        $activo = $request->boolean('activo');

        return $this->ok($this->unidades->cambiarEstado($unidad, $activo), $activo ? 'Unidad reactivada.' : 'Unidad dada de baja.');
    }
}
