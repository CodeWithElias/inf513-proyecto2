<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CanalRequest;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\PlataformaRequest;
use App\Models\CanalDifusion;
use App\Services\CanalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Plataformas (CU2) y canales de difusión de TV, radio y web. */
class CanalController extends Controller
{
    public function __construct(private readonly CanalService $canales)
    {
    }

    public function plataformas(): JsonResponse
    {
        return $this->ok($this->canales->listarPlataformas());
    }

    public function crearPlataforma(PlataformaRequest $request): JsonResponse
    {
        return $this->creado($this->canales->crearPlataforma($request->validated('nombre')), 'Plataforma registrada correctamente.');
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->canales->listarCanales($request->query(), $this->porPagina($request)));
    }

    public function store(CanalRequest $request): JsonResponse
    {
        return $this->creado($this->canales->crearCanal($request->validated()), 'Canal registrado correctamente.');
    }

    public function estado(EstadoRequest $request, CanalDifusion $canal): JsonResponse
    {
        $activo = $request->boolean('activo');

        return $this->ok($this->canales->cambiarEstadoCanal($canal, $activo), $activo ? 'Canal reactivado.' : 'Canal dado de baja.');
    }
}
