<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RechazarSolicitudRequest;
use App\Models\SolicitudCuenta;
use App\Services\SolicitudCuentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Solicitudes de cuenta de visitantes (Director / Administrador). */
class SolicitudController extends Controller
{
    public function __construct(private readonly SolicitudCuentaService $solicitudes)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['estado' => ['nullable', 'in:pendiente,aprobada,rechazada']]);

        return $this->paginado($this->solicitudes->listar($request->query(), $this->porPagina($request)));
    }

    /** GET /solicitudes/contador -> { pendientes } (para el contador del menú lateral) */
    public function contador(): JsonResponse
    {
        return $this->ok(['pendientes' => SolicitudCuenta::where('estado', SolicitudCuenta::PENDIENTE)->count()]);
    }

    public function aprobar(Request $request, SolicitudCuenta $solicitud): JsonResponse
    {
        $s = $this->solicitudes->aprobar($solicitud, $request->user());

        return $this->ok($s, "Cuenta creada: {$s->visitante->nombre} ya puede iniciar sesión como cliente con su correo y la contraseña que eligió.");
    }

    public function rechazar(RechazarSolicitudRequest $request, SolicitudCuenta $solicitud): JsonResponse
    {
        return $this->ok($this->solicitudes->rechazar($solicitud, $request->validated('motivo'), $request->user()), 'Solicitud rechazada.');
    }
}
