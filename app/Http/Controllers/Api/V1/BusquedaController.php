<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BusquedaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusquedaController extends Controller
{
    public function __construct(private readonly BusquedaService $busqueda)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        return $this->ok($this->busqueda->buscar($q, $request->user()));
    }
}

