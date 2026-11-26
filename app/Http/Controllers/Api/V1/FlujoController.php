<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FlujoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Avance del flujo de contratación (cliente) y tareas pendientes (personal), para Inicio. */
class FlujoController extends Controller
{
    public function __construct(private readonly FlujoService $flujo)
    {
    }

    /** GET /flujo → { contrataciones: [...], pendientes: [...] } según el rol. */
    public function resumen(Request $request): JsonResponse
    {
        return $this->ok($this->flujo->resumen($request->user()));
    }
}
