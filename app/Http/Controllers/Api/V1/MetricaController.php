<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetricaRequest;
use App\Services\MetricaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricaController extends Controller
{
    public function __construct(private readonly MetricaService $metricas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado(
            $this->metricas->listar($request->query(), $this->porPagina($request), $request->user())
        );
    }

    public function store(MetricaRequest $request): JsonResponse
    {
        return $this->creado(
            $this->metricas->registrar($request->validated(), $request->user()),
            'Métricas de impacto registradas correctamente.'
        );
    }

    public function resumen(Request $request): JsonResponse
    {
        return $this->ok(
            $this->metricas->resumen($request->user())
        );
    }
}
