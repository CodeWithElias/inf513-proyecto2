<?php

namespace App\Http\Middleware;

use App\Models\Bitacora;
use App\Services\BitacoraService;
use Closure;
use Illuminate\Support\Str;
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
        'api/v1/solicitudes/contador', 'api/v1/notificaciones/contador', 'api/v1/flujo']; // se consulta cada minuto para el contador del menú

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
            'detalle' => $request->isMethod('GET') ? null : $this->detalleCambio($request, $respuesta),
        ]);

        return $respuesta;
    }

    /** Campos que explican un cambio (nunca contraseñas, números de tarjeta ni CVV). */
    private const CAMPOS_TRAZA = ['estado', 'resultado', 'activo', 'motivo', 'observaciones', 'nombre', 'razon_social',
        'nro_proforma', 'id_proforma', 'id_campania', 'id_material', 'id_espacio', 'id_detalle', 'id_metodo', 'id_rol',
        'id_empresa', 'nro_cuotas', 'frecuencia', 'tipo', 'monto', 'cantidad', 'fecha', 'hora_inicio', 'hora_fin',
        'fecha_inicio', 'fecha_fin', 'url_publicacion', 'descuento'];

    /**
     * Trazabilidad: qué se hizo (ids de la ruta + campos clave) y cómo terminó (mensaje de la respuesta).
     * Ej.: «proforma=17 · estado=aprobada → Proforma aprobada. Se abrió su campaña…»
     */
    private function detalleCambio(Request $request, Response $respuesta): ?string
    {
        $partes = [];
        foreach ($request->route()?->parameters() ?? [] as $nombre => $valor) {
            $partes[] = $nombre.'='.(is_object($valor) && method_exists($valor, 'getKey') ? $valor->getKey() : (string) $valor);
        }
        foreach (self::CAMPOS_TRAZA as $campo) {
            $v = $request->input($campo);
            if ($v === null || $v === '' || is_array($v)) {
                continue;
            }
            $partes[] = $campo.'='.Str::limit(is_bool($v) ? ($v ? 'sí' : 'no') : (string) $v, 60);
        }
        if (is_array($request->input('detalles'))) {
            $partes[] = count($request->input('detalles')).' ítem(s)';
        }
        if ($request->hasFile('archivo')) {
            $partes[] = 'archivo='.Str::limit($request->file('archivo')->getClientOriginalName(), 60);
        }
        $mensaje = null;
        if (str_contains((string) $respuesta->headers->get('Content-Type'), 'json')) {
            $json = json_decode((string) $respuesta->getContent(), true);
            $mensaje = $json['message'] ?? null;
            // Alta: se anota el registro creado (su id y, si tiene, su número)
            if ($respuesta->getStatusCode() === 201 && is_array($json['data'] ?? null)) {
                // En un registro recién creado, Eloquent agrega la clave primaria al final de sus atributos
                $ids = collect($json['data'])->filter(fn ($v, $k) => str_starts_with((string) $k, 'id_') && is_scalar($v));
                if ($ids->isNotEmpty()) {
                    $clave = $ids->keys()->last();
                    array_unshift($partes, 'nuevo '.$clave.'='.$ids->last().(isset($json['data']['nro_proforma']) ? " ({$json['data']['nro_proforma']})" : ''));
                }
            }
        }
        $texto = implode(' · ', $partes).($mensaje ? ' → '.$mensaje : '');

        return $texto === '' ? null : Str::limit($texto, 495, '…');
    }
}
