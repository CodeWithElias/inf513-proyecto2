<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EspacioRequest;
use App\Models\EspacioPublicitario;
use App\Services\EspacioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EspacioController extends Controller
{
    public function __construct(private readonly EspacioService $espacios)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado(
            $this->espacios->listar($request->query(), $this->porPagina($request))
        );
    }

    public function store(EspacioRequest $request): JsonResponse
    {
        return $this->creado(
            $this->espacios->crear($request->validated()),
            'Espacio publicitario creado correctamente.'
        );
    }

    public function update(EspacioRequest $request, EspacioPublicitario $espacio): JsonResponse
    {
        return $this->ok(
            $this->espacios->actualizar($espacio, $request->validated()),
            'Espacio publicitario actualizado correctamente.'
        );
    }

    public function destroy(EspacioPublicitario $espacio): JsonResponse
    {
        $this->espacios->eliminar($espacio);

        return $this->ok(null, 'Espacio publicitario eliminado correctamente.');
    }
}
