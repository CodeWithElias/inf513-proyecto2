<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Proforma;
use App\Services\ContratacionService;
use App\Support\ArchivoMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contratación guiada de una proforma (única forma de aprobarla).
 *   GET  contrataciones/{proforma}                        avance de los 4 pasos
 *   PUT  contrataciones/{proforma}/campania               paso 1
 *   POST contrataciones/{proforma}/lineas/{linea}/material paso 2 (archivo, enlace o material ya cargado)
 *   GET  contrataciones/{proforma}/lineas/{linea}/espacios paso 3: agenda disponible
 *   PUT  contrataciones/{proforma}/lineas/{linea}/espacios paso 3: horarios elegidos
 *   POST contrataciones/{proforma}/confirmar              paso 4: pago → proforma aprobada
 */
class ContratacionController extends Controller
{
    public function __construct(private readonly ContratacionService $contratacion)
    {
    }

    public function show(Request $request, Proforma $proforma): JsonResponse
    {
        return $this->ok($this->contratacion->estado($proforma, $request->user()));
    }

    public function campania(Request $request, Proforma $proforma): JsonResponse
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ], [], ['nombre' => 'nombre de la campaña', 'fecha_inicio' => 'fecha de inicio', 'fecha_fin' => 'fecha de fin']);

        return $this->ok($this->contratacion->guardarCampania($proforma, $d, $request->user()), 'Datos de la campaña guardados.');
    }

    public function material(Request $request, Proforma $proforma, int $linea): JsonResponse
    {
        $d = $request->validate([
            'id_material' => ['nullable', 'integer'],
            'tipo' => ['required_without:id_material', 'nullable', 'string'],
            'nombre_archivo' => ['nullable', 'string', 'max:255'],
            'url_archivo' => ['nullable', 'string', 'max:500'],
            'duracion_seg' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'texto_publicacion' => ['nullable', 'string', 'max:3000'],
            'archivo' => ['nullable', 'file', 'max:'.ArchivoMaterial::MAX_KB],
        ], [], ['tipo' => 'tipo de material', 'duracion_seg' => 'duración', 'texto_publicacion' => 'texto de la publicación']);
        if ($request->hasFile('archivo')) {
            $d['archivo'] = $request->file('archivo');
        }

        return $this->ok($this->contratacion->asignarMaterial($proforma, $linea, $d, $request->user()), 'Material asignado al servicio.');
    }

    public function espacios(Request $request, Proforma $proforma, int $linea): JsonResponse
    {
        return $this->ok($this->contratacion->espaciosDisponibles($proforma, $linea, $request->user()));
    }

    public function reservar(Request $request, Proforma $proforma, int $linea): JsonResponse
    {
        $d = $request->validate(['espacios' => ['present', 'array'], 'espacios.*' => ['integer']]);

        return $this->ok($this->contratacion->reservar($proforma, $linea, $d['espacios'], $request->user()), 'Horarios apartados.');
    }

    public function confirmar(Request $request, Proforma $proforma): JsonResponse
    {
        $d = $request->validate([
            'modalidad' => ['required', 'in:contado,cuotas'],
            'id_metodo' => ['required', 'integer'],
            'nro_cuotas' => ['required_if:modalidad,cuotas', 'nullable', 'integer', 'min:2', 'max:24'],
            'frecuencia' => ['nullable', 'in:mensual,quincenal,semanal'],
            'payment_intent' => ['nullable', 'string', 'starts_with:pi_', 'max:80'],
        ], [], ['modalidad' => 'forma de pago', 'id_metodo' => 'método de pago', 'nro_cuotas' => 'número de cuotas']);

        return $this->ok($this->contratacion->confirmar($proforma, $d, $request->user()),
            'Contratación confirmada: la proforma quedó aprobada y sus emisiones programadas.');
    }
}
