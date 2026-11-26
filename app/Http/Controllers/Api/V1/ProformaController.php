<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CotizacionPublicaRequest;
use App\Http\Requests\EstadoProformaRequest;
use App\Http\Requests\ProformaRequest;
use App\Models\Proforma;
use App\Services\ProformaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProformaController extends Controller
{
    public function __construct(private readonly ProformaService $proformas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->proformas->listar($request->query(), $this->porPagina($request), $request->user()));
    }

    public function show(Request $request, Proforma $proforma): JsonResponse
    {
        return $this->ok($this->proformas->obtener($proforma, $request->user()));
    }

    public function store(ProformaRequest $request): JsonResponse
    {
        return $this->creado($this->proformas->crear($request->validated(), $request->user()), 'Proforma generada correctamente.');
    }

    public function update(ProformaRequest $request, Proforma $proforma): JsonResponse
    {
        return $this->ok($this->proformas->actualizar($proforma, $request->validated(), $request->user()), 'Proforma actualizada correctamente.');
    }

    public function estado(EstadoProformaRequest $request, Proforma $proforma): JsonResponse
    {
        $estado = $request->validated('estado');
        $p = $this->proformas->cambiarEstado($proforma, $estado, $request->user());

        return $this->ok($p, $estado === 'aprobada' ? 'Proforma aprobada correctamente.' : 'Proforma rechazada.');
    }

    public function destroy(Request $request, Proforma $proforma): JsonResponse
    {
        $this->proformas->eliminar($proforma, $request->user());

        return $this->ok(null, 'Proforma eliminada correctamente.');
    }

    /** B3 - Catálogo público (sin sesión). */
    public function catalogoPublico(): JsonResponse
    {
        return $this->ok($this->proformas->catalogoPublico());
    }

    /** B3 - Solicitud de proforma pública por un visitante (sin sesión). */
    public function cotizarPublica(CotizacionPublicaRequest $request): JsonResponse
    {
        $proforma = $this->proformas->cotizarPublica($request->validated());
        $mensaje = 'Cotización generada correctamente.';
        if ($proforma->solicitud_cuenta ?? null) {
            $mensaje .= ' Su solicitud de cuenta quedó pendiente: cuando Canal 11 la apruebe podrá iniciar sesión con su correo y la contraseña que eligió.';
        }

        return $this->creado($proforma, $mensaje);
    }
}

