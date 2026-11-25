<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Visitante;
use App\Services\VisitanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitanteController extends Controller
{
    public function __construct(private readonly VisitanteService $visitantes)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->visitantes->listar($request->query(), $this->porPagina($request)));
    }

    public function show(Visitante $visitante): JsonResponse
    {
        return $this->ok($this->visitantes->obtener($visitante));
    }
}

