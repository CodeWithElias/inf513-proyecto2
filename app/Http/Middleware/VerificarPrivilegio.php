<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Matriz de control de acceso. Uso en rutas:
 *   ->middleware('permiso:usuarios.ver')
 *   ->middleware('permiso:pagos.ver|pagos.crear')   (basta con uno)
 * Responde 403 si el rol del usuario no tiene el privilegio. La bitácora
 * lo registra como evento "denegado".
 */
class VerificarPrivilegio
{
    public function handle(Request $request, Closure $next, string $codigos): Response
    {
        $usuario = $request->user();
        if (! $usuario) {
            return response()->json(['message' => 'Debe iniciar sesión para continuar.'], 401);
        }

        foreach (explode('|', $codigos) as $codigo) {
            if ($usuario->tienePrivilegio(trim($codigo))) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'No tiene permiso para realizar esta acción. Si cree que es un error, consulte al administrador.',
        ], 403);
    }
}
