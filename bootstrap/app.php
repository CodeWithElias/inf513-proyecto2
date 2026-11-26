<?php

use App\Exceptions\NegocioException;
use App\Exceptions\PagoRequiereAccion;
use App\Http\Middleware\RegistrarBitacora;
use App\Http\Middleware\VerificarPrivilegio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El servidor de la facultad puede estar detrás de un proxy: tomar la IP real.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['permiso' => VerificarPrivilegio::class]);
        // Sin sesión: la API responde 401 JSON; si no se pidió JSON, se va al login de React.
        $middleware->redirectGuestsTo('/login');
        $middleware->api(append: [RegistrarBitacora::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toda respuesta de /api es JSON con mensajes en español.
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());

        $json = fn (string $mensaje, int $status, array $extra = []) => response()->json(['message' => $mensaje] + $extra, $status);

        // 3D Secure: el navegador debe completar la verificación de Stripe y reenviar el pago con payment_intent
        $exceptions->render(fn (PagoRequiereAccion $e) => $json($e->getMessage(), 202, ['data' => [
            'requiere_accion' => true, 'client_secret' => $e->clientSecret, 'payment_intent' => $e->paymentIntent,
        ]]));

        $exceptions->render(function (NegocioException $e) use ($json) {
            return $json($e->getMessage(), $e->status());
        });

        $exceptions->render(function (ValidationException $e, Request $r) use ($json) {
            if (! $r->is('api/*')) {
                return null;
            }

            return $json('Revise los datos del formulario: hay campos con errores.', 422, ['errors' => $e->errors()]);
        });

        $exceptions->render(function (AuthenticationException $e, Request $r) use ($json) {
            return $r->is('api/*') ? $json('Su sesión expiró o no ha iniciado sesión. Ingrese nuevamente.', 401) : null;
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $r) use ($json) {
            return $r->is('api/*') ? $json('No tiene permiso para realizar esta acción.', 403) : null;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $r) use ($json) {
            return $r->is('api/*') ? $json('El recurso solicitado no existe o fue eliminado.', 404) : null;
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $r) use ($json) {
            return $r->is('api/*') ? $json('Operación no permitida sobre este recurso.', 405) : null;
        });

        $exceptions->render(function (InvalidSignatureException $e, Request $r) use ($json) {
            return $json('El enlace del archivo venció o no es válido. Recargue la página para obtener uno nuevo.', 403);
        });

        $exceptions->render(function (PostTooLargeException $e, Request $r) use ($json) {
            return $json('El archivo es demasiado grande. El máximo permitido es 20 MB.', 413);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $r) use ($json) {
            return $r->is('api/*') ? $json('Demasiadas solicitudes. Espere un momento e inténtelo de nuevo.', 429) : null;
        });

        $exceptions->render(function (QueryException $e, Request $r) use ($json) {
            if (! $r->is('api/*')) {
                return null;
            }
            Log::error('Error de base de datos: '.$e->getMessage());

            return match ($e->getCode()) {
                '23505' => $json('Ya existe un registro con esos datos.', 422),
                '23503' => $json('No se puede completar la operación: el registro está relacionado con otros datos.', 422),
                '22001' => $json('Uno de los textos supera la longitud permitida.', 422),
                default => $json('Ocurrió un error al acceder a la base de datos. Inténtelo más tarde.', 500),
            };
        });

        $exceptions->render(function (Throwable $e, Request $r) use ($json) {
            if (! $r->is('api/*') || $e instanceof HttpExceptionInterface || config('app.debug')) {
                return null;
            }

            return $json('Ocurrió un error inesperado. Inténtelo más tarde.', 500);
        });
    })->create();
