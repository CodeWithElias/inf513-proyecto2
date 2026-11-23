<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Bitacora;
use App\Models\Usuario;
use App\Support\Pbkdf2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthService
{
    private const MAX_INTENTOS = 5;      // por email + IP
    private const BLOQUEO_SEGUNDOS = 60;

    public function __construct(
        private readonly BitacoraService $bitacora,
        private readonly MenuService $menu,
    ) {
    }

    /** Valida credenciales, registra el intento en la bitácora y devuelve el token. */
    public function login(Request $request, string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $clave = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $seg = RateLimiter::availableIn($clave);
            $this->fallido($request, $email, 'Bloqueado por demasiados intentos');
            throw new NegocioException("Demasiados intentos fallidos. Espere {$seg} segundos e inténtelo de nuevo.", 429);
        }

        $usuario = Usuario::with('rol')->whereRaw('lower(email) = ?', [$email])->first();

        if (! $usuario || ! Pbkdf2::verificar($password, $usuario->password_hash)) {
            RateLimiter::hit($clave, self::BLOQUEO_SEGUNDOS);
            $this->fallido($request, $email, $usuario ? 'Contraseña incorrecta' : 'Email no registrado');
            throw new NegocioException('El correo o la contraseña son incorrectos.', 401);
        }
        if (! $usuario->activo) {
            $this->fallido($request, $email, 'Usuario inactivo', $usuario);
            throw new NegocioException('Su cuenta está dada de baja. Comuníquese con el administrador.', 403);
        }
        if (! $usuario->rol?->activo) {
            $this->fallido($request, $email, 'Rol inactivo', $usuario);
            throw new NegocioException('Su rol está inactivo. Comuníquese con el administrador.', 403);
        }

        RateLimiter::clear($clave);
        $token = $usuario->createToken('spa')->plainTextToken;
        $this->bitacora->registrar($request, Bitacora::LOGIN_OK, $usuario, [
            'recurso' => 'POST api/v1/auth/login',
            'codigo_http' => 200,
        ]);

        return ['token' => $token, 'sesion' => $this->sesion($usuario)];
    }

    public function logout(Request $request, Usuario $usuario): void
    {
        $usuario->currentAccessToken()?->delete();
        $this->bitacora->registrar($request, Bitacora::LOGOUT, $usuario, [
            'recurso' => 'POST api/v1/auth/logout',
            'codigo_http' => 200,
        ]);
    }

    /** Datos que el frontend necesita tras el login: usuario, privilegios y menú. */
    public function sesion(Usuario $usuario): array
    {
        $usuario->loadMissing(['rol', 'unidad', 'representante.empresa']);

        return [
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre_completo' => $usuario->nombre_completo,
                'email' => $usuario->email,
                'telefono' => $usuario->telefono,
                'rol' => $usuario->rol?->nombre,
                'id_rol' => $usuario->id_rol,
                'unidad' => $usuario->unidad?->nombre,
                'id_unidad' => $usuario->id_unidad,
                'empresa' => $usuario->representante?->empresa?->razon_social,
                'id_empresa' => $usuario->representante?->id_empresa,
                'ultimo_acceso' => $usuario->ultimo_acceso,
            ],
            'privilegios' => $usuario->privilegios(),
            'menu' => $this->menu->para($usuario),
        ];
    }

    private function fallido(Request $request, string $email, string $motivo, ?Usuario $usuario = null): void
    {
        $this->bitacora->registrar($request, Bitacora::LOGIN_FALLIDO, null, [
            'id_usuario' => $usuario?->id_usuario,
            'email' => mb_substr($email, 0, 150),
            'recurso' => 'POST api/v1/auth/login',
            'codigo_http' => 401,
            'detalle' => $motivo,
        ]);
    }
}
