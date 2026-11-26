<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * C2 / CU5: Gestión de Notificaciones del Sistema (NNotificacion.java).
 */
class NotificacionService
{
    /** Listado de notificaciones para el usuario autenticado (o filtrado para directores). */
    public function listar(Usuario $actor, array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        $q = Notificacion::query()
            ->with([
                'pauta.espacio.canal:id_canal,nombre,tipo',
                'pauta.material:id_material,nombre_archivo',
            ]);

        // Si no es Personal (Admin/Director), solo consulta las suyas
        if (! $actor->esPersonal()) {
            $q->where('id_usuario', $actor->id_usuario);
        } elseif (! empty($filtros['id_usuario'])) {
            $q->where('id_usuario', (int) $filtros['id_usuario']);
        }

        if (isset($filtros['leida']) && $filtros['leida'] !== '') {
            $q->where('leida', filter_var($filtros['leida'], FILTER_VALIDATE_BOOLEAN));
        }

        return $q->orderBy('leida')->orderByDesc('fecha_envio')->paginate($porPagina);
    }

    /** Marcar una notificación individual como leída. */
    public function marcarLeida(Notificacion $notificacion, Usuario $actor): Notificacion
    {
        if (! $actor->esPersonal() && (int) $notificacion->id_usuario !== (int) $actor->id_usuario) {
            throw new NegocioException('Solo puede modificar sus propias notificaciones.', 403);
        }

        if (! $notificacion->leida) {
            $notificacion->update(['leida' => true]);
        }

        return $notificacion;
    }

    /** Notificaciones sin leer del usuario (número de la campana del encabezado). */
    public function contarNoLeidas(Usuario $actor): int
    {
        return Notificacion::query()
            ->where('id_usuario', $actor->id_usuario)
            ->where('leida', false)
            ->count();
    }

    /** Marcar todas las notificaciones pendientes del usuario como leídas. */
    public function marcarTodasLeidas(Usuario $actor): int
    {
        return Notificacion::query()
            ->where('id_usuario', $actor->id_usuario)
            ->where('leida', false)
            ->update(['leida' => true]);
    }
}
