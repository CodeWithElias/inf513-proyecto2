<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RepresentanteRequest;
use App\Models\Representante;
use App\Services\RepresentanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepresentanteController extends Controller
{
    public function __construct(private readonly RepresentanteService $representantes)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->representantes->listar($request->query(), $this->porPagina($request)));
    }

    public function show(Representante $representante): JsonResponse
    {
        return $this->ok($representante->load(['usuario:id_usuario,nombre_completo,email,telefono,activo', 'empresa:id_empresa,razon_social,nit']));
    }

    public function store(RepresentanteRequest $request): JsonResponse
    {
        return $this->creado($this->representantes->crear($request->validated()), 'Cliente registrado correctamente.');
    }

    public function update(RepresentanteRequest $request, Representante $representante): JsonResponse
    {
        return $this->ok($this->representantes->actualizar($representante, $request->validated()), 'Cliente actualizado correctamente.');
    }

    public function destroy(Representante $representante): JsonResponse
    {
        $this->representantes->eliminar($representante);

        return $this->ok(null, 'El usuario ya no está registrado como cliente.');
    }
}
