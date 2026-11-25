<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmpresaRequest;
use App\Http\Requests\EstadoRequest;
use App\Models\EmpresaAnunciante;
use App\Services\EmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function __construct(private readonly EmpresaService $empresas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->empresas->listar($request->query(), $this->porPagina($request), $request->user()));
    }

    public function show(Request $request, EmpresaAnunciante $empresa): JsonResponse
    {
        return $this->ok($this->empresas->obtener($empresa, $request->user()));
    }

    public function store(EmpresaRequest $request): JsonResponse
    {
        return $this->creado($this->empresas->crear($request->validated()), 'Empresa anunciante registrada correctamente.');
    }

    public function update(EmpresaRequest $request, EmpresaAnunciante $empresa): JsonResponse
    {
        return $this->ok($this->empresas->actualizar($empresa, $request->validated()), 'Empresa anunciante actualizada correctamente.');
    }

    public function estado(EstadoRequest $request, EmpresaAnunciante $empresa): JsonResponse
    {
        $activo = $request->boolean('activo');
        $e = $this->empresas->cambiarEstado($empresa, $activo);

        return $this->ok($e, $activo ? 'Empresa reactivada.' : 'Empresa dada de baja.');
    }
}

