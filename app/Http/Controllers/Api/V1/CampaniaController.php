<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CampaniaRequest;
use App\Models\Campania;
use App\Services\CampaniaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaniaController extends Controller
{
    public function __construct(private readonly CampaniaService $campanias)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->campanias->listar($request->query(), $this->porPagina($request), $request->user()));
    }

    public function show(Request $request, Campania $campania): JsonResponse
    {
        return $this->ok($this->campanias->obtener($campania, $request->user()));
    }

    public function store(CampaniaRequest $request): JsonResponse
    {
        return $this->creado($this->campanias->crear($request->validated()), 'Campaña registrada correctamente.');
    }

    public function update(CampaniaRequest $request, Campania $campania): JsonResponse
    {
        return $this->ok($this->campanias->actualizar($campania, $request->validated(), $request->user()), 'Campaña actualizada correctamente.');
    }
}

