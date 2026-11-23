<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Formato estándar de la API (todos los controladores lo usan):
 *   éxito:     { "data": ..., "message"?: "..." }
 *   paginado:  { "data": [...], "meta": { current_page, last_page, per_page, total } }
 *   error:     { "message": "...", "errors"?: { campo: ["..."] } }   (bootstrap/app.php)
 */
abstract class Controller
{
    protected function ok(mixed $data = null, ?string $mensaje = null, int $status = 200): JsonResponse
    {
        $cuerpo = ['data' => $data];
        if ($mensaje !== null) {
            $cuerpo['message'] = $mensaje;
        }

        return response()->json($cuerpo, $status);
    }

    protected function creado(mixed $data, string $mensaje): JsonResponse
    {
        return $this->ok($data, $mensaje, 201);
    }

    protected function paginado(LengthAwarePaginator $p, ?callable $transformar = null): JsonResponse
    {
        $items = $transformar ? collect($p->items())->map($transformar)->values() : $p->items();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    /** per_page de la petición, entre 1 y 100 (por defecto 10). */
    protected function porPagina(Request $request): int
    {
        return max(1, min(100, (int) $request->query('per_page', 10)));
    }
}
