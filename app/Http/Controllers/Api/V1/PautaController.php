<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PautaRequest;
use App\Models\ProgramacionPauta;
use App\Services\PautaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PautaController extends Controller
{
    public function __construct(private readonly PautaService $pautas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado(
            $this->pautas->listar($request->query(), $this->porPagina($request), $request->user())
        );
    }

    public function store(PautaRequest $request): JsonResponse
    {
        return $this->creado(
            $this->pautas->programar($request->validated(), $request->user()),
            'Pauta publicitaria programada correctamente.'
        );
    }

    public function reprogramar(Request $request, ProgramacionPauta $pauta): JsonResponse
    {
        $request->validate(['id_espacio' => ['required', 'integer']]);

        return $this->ok(
            $this->pautas->reprogramar($pauta, (int) $request->input('id_espacio'), $request->user()),
            'Pauta reprogramada correctamente.'
        );
    }

    public function emitir(Request $request, ProgramacionPauta $pauta): JsonResponse
    {
        $request->validate(['url_publicacion' => ['nullable', 'string', 'max:500']]);

        return $this->ok(
            $this->pautas->emitir($pauta, $request->input('url_publicacion'), $request->user()),
            'Pauta marcada como emitida/publicada.'
        );
    }

    public function destroy(Request $request, ProgramacionPauta $pauta): JsonResponse
    {
        return $this->ok(
            $this->pautas->cancelar($pauta, $request->user()),
            'Pauta cancelada y espacio liberado correctamente.'
        );
    }
}
