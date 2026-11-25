<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EstadisticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstadisticaController extends Controller
{
    public function __construct(private readonly EstadisticaService $estadisticas)
    {
    }

    public function negocio(Request $request): JsonResponse
    {
        return $this->ok(
            $this->estadisticas->obtenerEstadisticasNegocio($request->user())
        );
    }
}
