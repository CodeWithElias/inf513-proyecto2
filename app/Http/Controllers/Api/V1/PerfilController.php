<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarPasswordRequest;
use App\Http\Requests\MiEmpresaRequest;
use App\Http\Requests\PerfilRequest;
use App\Services\AuthService;
use App\Services\PerfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Mi perfil" de cualquier usuario con sesión (no requiere privilegios: cada uno edita lo suyo). */
class PerfilController extends Controller
{
    public function __construct(private readonly PerfilService $perfil, private readonly AuthService $auth)
    {
    }

    /** PUT /auth/perfil -> devuelve la sesión actualizada */
    public function actualizar(PerfilRequest $request): JsonResponse
    {
        $this->perfil->actualizar($request->user(), $request->validated());

        return $this->ok($this->auth->sesion($request->user()->fresh()), 'Sus datos fueron actualizados.');
    }

    /** PUT /auth/password */
    public function password(CambiarPasswordRequest $request): JsonResponse
    {
        $this->perfil->cambiarPassword($request->user(), $request->input('password_actual'), $request->input('password'));

        return $this->ok(null, 'Contraseña actualizada. Se cerraron sus otras sesiones abiertas.');
    }

    /** GET /mi-empresa */
    public function miEmpresa(Request $request): JsonResponse
    {
        return $this->ok($this->perfil->miEmpresa($request->user()));
    }

    /** POST /mi-empresa -> registra la empresa y devuelve la sesión actualizada */
    public function registrarEmpresa(MiEmpresaRequest $request): JsonResponse
    {
        $this->perfil->registrarEmpresa($request->user(), $request->validated());

        return $this->creado($this->auth->sesion($request->user()->fresh()),
            'Su empresa fue registrada y usted quedó como su representante. Sus proformas anteriores ahora también están a nombre de la empresa.');
    }
}
