<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadoRequest;
use App\Http\Requests\MetodoPagoRequest;
use App\Models\MetodoPago;
use App\Services\MetodoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    public function __construct(private readonly MetodoPagoService $metodos)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginado($this->metodos->listar($request->query(), $this->porPagina($request), $request->user()));
    }

    /** POST /metodos-pago/preparar → client_secret de un SetupIntent de Stripe (paso 1 del registro). */
    public function preparar(Request $request): JsonResponse
    {
        return $this->ok($this->metodos->prepararRegistro($request->user()));
    }

    public function store(MetodoPagoRequest $request): JsonResponse
    {
        return $this->creado($this->metodos->crear($request->validated(), $request->user()), 'Tarjeta registrada correctamente.');
    }

    public function update(MetodoPagoRequest $request, MetodoPago $metodo): JsonResponse
    {
        return $this->ok($this->metodos->actualizar($metodo, $request->validated(), $request->user()), 'Método de pago actualizado correctamente.');
    }

    public function estado(EstadoRequest $request, MetodoPago $metodo): JsonResponse
    {
        $activo = $request->boolean('activo');
        $m = $this->metodos->cambiarEstado($metodo, $activo, $request->user());

        return $this->ok($m, $activo ? 'Método de pago reactivado.' : 'Método de pago dado de baja.');
    }
}

