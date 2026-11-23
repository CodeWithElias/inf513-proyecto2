<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BitacoraController extends Controller
{
    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    /** GET /bitacora?evento=&ip=&q=&desde=&hasta=&page= */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'evento' => ['nullable', 'in:login_ok,login_fallido,logout,acceso,denegado,error'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        return $this->paginado($this->bitacora->listar($request->query(), $this->porPagina($request)));
    }

    /** GET /bitacora/resumen?dias=14  (también lo usa el dashboard) */
    public function resumen(Request $request): JsonResponse
    {
        $request->validate(['dias' => ['nullable', 'integer', 'min:1', 'max:90']]);

        return $this->ok($this->bitacora->resumen((int) $request->query('dias', 14)));
    }
}
