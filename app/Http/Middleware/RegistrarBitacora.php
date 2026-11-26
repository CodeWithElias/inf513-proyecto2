<?php

namespace App\Http\Middleware;

use App\Models\Bitacora;
use App\Services\BitacoraService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra en la tabla bitacora cada petición a la API: usuario, IP,
 * recurso (método + patrón de ruta), código HTTP y duración.
 *   2xx-4xx -> acceso  |  403 -> denegado  |  5xx -> error
 * El login y el logout los registra AuthService con su propio evento.
 */
class RegistrarBitacora
{
    /** Rutas que no se registran (ruido o se registran aparte). */
    private const OMITIDAS = ['api/v1/visitas', 'api/v1/auth/login', 'api/v1/auth/logout',
        'api/v1/solicitudes/contador', 'api/v1/notificaciones/contador']; // se consulta cada minuto para el contador del menú

    public function __construct(private readonly BitacoraService $bitacora)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);
        $respuesta = $next($request);

        $uri = $request->route()?->uri();
        if ($uri === null || in_array($uri, self::OMITIDAS, true) || $request->isMethod('OPTIONS')) {
            return $respuesta;
        }

        $codigo = $respuesta->getStatusCode();
        $evento = match (true) {
            $codigo === 403 => Bitacora::DENEGADO,
            $codigo >= 500 => Bitacora::ERROR,
            default => Bitacora::ACCESO,
        };

        $usuario = $request->user('sanctum');
        $this->bitacora->registrar($request, $evento, $usuario, [
            'recurso' => $request->method().' '.$uri,
            'codigo_http' => $codigo,
            'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ]);

        return $respuesta;
    }
}
