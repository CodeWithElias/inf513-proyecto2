<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /** POST /auth/login  { email, password } -> { token, sesion } */
    public function login(LoginRequest $request): JsonResponse
    {
        $r = $this->auth->login($request, $request->input('email'), $request->input('password'));

        return $this->ok($r, 'Bienvenido, '.$r['sesion']['usuario']['nombre_completo'].'.');
    }

    /** GET /auth/me -> usuario, privilegios y menú dinámico */
    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->auth->sesion($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request, $request->user());

        return $this->ok(null, 'Sesión cerrada correctamente.');
    }
}
