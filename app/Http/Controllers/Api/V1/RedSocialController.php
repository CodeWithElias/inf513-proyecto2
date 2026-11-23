<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\RedSocialRequest;
use App\Http\Requests\SeguidoresRequest;
use App\Models\RedSocial;
use App\Services\RedSocialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedSocialController extends Controller
{
    public function __construct(private readonly RedSocialService $redes)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->redes->listar($request->query(), $this->porPagina($request), $request->user()));
    }

    public function show(Request $request, RedSocial $red): JsonResponse
    {
        return $this->ok($this->redes->obtener($red, $request->user()));
    }

    public function store(RedSocialRequest $request): JsonResponse
    {
        return $this->creado($this->redes->crear($request->validated()), 'Red social registrada correctamente.');
    }

    public function update(RedSocialRequest $request, RedSocial $red): JsonResponse
    {
        return $this->ok($this->redes->actualizar($red, $request->validated()), 'Red social actualizada correctamente.');
    }

    public function estado(EstadoRequest $request, RedSocial $red): JsonResponse
    {
        $activo = $request->boolean('activo');

        return $this->ok($this->redes->cambiarEstado($red, $activo), $activo ? 'Red social reactivada.' : 'Red social dada de baja.');
    }

    public function seguidores(Request $request, RedSocial $red): JsonResponse
    {
        return $this->ok($this->redes->seguidores($red, $request->user()));
    }

    public function registrarSeguidores(SeguidoresRequest $request, RedSocial $red): JsonResponse
    {
        $s = $this->redes->registrarSeguidores($red, (int) $request->validated('seguidores'), $request->validated('fecha'), $request->user());

        return $this->creado($s, 'Medición de seguidores registrada.');
    }
}
