<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PagarCuotaRequest;
use App\Http\Requests\PagarProformaRequest;
use App\Models\Cuota;
use App\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function __construct(private readonly PagoService $pagos)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->pagos->listarTransacciones($request->query(), $this->porPagina($request), $request->user()));
    }

    public function pagarProforma(PagarProformaRequest $request): JsonResponse
    {
        $tx = $this->pagos->pagarProforma($request->validated(), $request->user());

        return $this->creado($tx, $tx->estado === 'aprobada'
            ? "Pago aprobado (código {$tx->codigo_autorizacion})."
            : "Pago rechazado: {$tx->mensaje}");
    }

    public function pagarCuota(PagarCuotaRequest $request, Cuota $cuota): JsonResponse
    {
        $tx = $this->pagos->pagarCuota($cuota, $request->validated(), $request->user());

        return $this->creado($tx, $tx->estado === 'aprobada'
            ? "Cuota pagada correctamente (código {$tx->codigo_autorizacion})."
            : "Pago de cuota rechazado: {$tx->mensaje}");
    }
}

