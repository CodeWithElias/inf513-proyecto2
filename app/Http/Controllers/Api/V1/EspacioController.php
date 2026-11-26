<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EspacioRequest;
use App\Models\EspacioPublicitario;
use App\Services\EspacioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EspacioController extends Controller
{
    public function __construct(private readonly EspacioService $espacios)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado(
            $this->espacios->listar($request->query(), $this->porPagina($request))
        );
    }

    public function store(EspacioRequest $request): JsonResponse
    {
        $d = $request->validated();
        if (! empty($d['fecha_hasta']) && $d['fecha_hasta'] !== $d['fecha']) {
            $r = $this->espacios->crearRango($d);

            return $this->creado($r, "Se crearon {$r['creados']} espacio(s)".($r['omitidos'] ? "; se omitieron {$r['omitidos']} día(s) que ya tenían ese horario ocupado" : '').'.');
        }

        return $this->creado($this->espacios->crear($d), 'Espacio publicitario creado correctamente.');
    }

    public function update(EspacioRequest $request, EspacioPublicitario $espacio): JsonResponse
    {
        return $this->ok(
            $this->espacios->actualizar($espacio, $request->validated()),
            'Espacio publicitario actualizado correctamente.'
        );
    }

    public function destroy(EspacioPublicitario $espacio): JsonResponse
    {
        $this->espacios->eliminar($espacio);

        return $this->ok(null, 'Espacio publicitario eliminado correctamente.');
    }
}
