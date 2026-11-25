<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlanPagoRequest;
use App\Models\PlanPago;
use App\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanPagoController extends Controller
{
    public function __construct(private readonly PagoService $pagos)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->pagos->listarPlanes($request->query(), $this->porPagina($request), $request->user()));
    }

    public function show(Request $request, PlanPago $plan): JsonResponse
    {
        return $this->ok($this->pagos->obtenerPlan($plan, $request->user()));
    }

    public function store(PlanPagoRequest $request): JsonResponse
    {
        return $this->creado($this->pagos->crearPlan($request->validated(), $request->user()), 'Plan de pagos generado correctamente.');
    }
}

