<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitaRequest;
use App\Services\VisitaService;
use Illuminate\Http\JsonResponse;

/** Contador de visitas del footer. Público (también cuenta visitas sin sesión). */
class VisitaController extends Controller
{
    public function __construct(private readonly VisitaService $visitas)
    {
    }

    /** POST /visitas { ruta: "/usuarios" } -> { ruta, total } */
    public function registrar(VisitaRequest $request): JsonResponse
    {
        $ruta = $request->input('ruta');

        return $this->ok(['ruta' => $ruta, 'total' => $this->visitas->registrar($ruta)]);
    }
}
