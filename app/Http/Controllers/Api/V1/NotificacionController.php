<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function __construct(private readonly NotificacionService $notificaciones)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado(
            $this->notificaciones->listar($request->user(), $request->query(), $this->porPagina($request))
        );
    }

    /** GET /notificaciones/contador → { no_leidas } (campana del encabezado). */
    public function contador(Request $request): JsonResponse
    {
        return $this->ok(['no_leidas' => $this->notificaciones->contarNoLeidas($request->user())]);
    }

    public function marcarLeida(Request $request, Notificacion $notificacion): JsonResponse
    {
        return $this->ok(
            $this->notificaciones->marcarLeida($notificacion, $request->user()),
            'Notificación marcada como leída.'
        );
    }

    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $total = $this->notificaciones->marcarTodasLeidas($request->user());

        return $this->ok(['actualizadas' => $total], 'Todas las notificaciones han sido marcadas como leídas.');
    }
}
