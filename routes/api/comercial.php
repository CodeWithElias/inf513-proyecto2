<?php

use App\Http\Controllers\Api\V1\BusquedaController;
use App\Http\Controllers\Api\V1\CampaniaController;
use App\Http\Controllers\Api\V1\EmpresaController;
use App\Http\Controllers\Api\V1\MetodoPagoController;
use App\Http\Controllers\Api\V1\PagoController;
use App\Http\Controllers\Api\V1\PaqueteController;
use App\Http\Controllers\Api\V1\PlanPagoController;
use App\Http\Controllers\Api\V1\ProformaController;
use App\Http\Controllers\Api\V1\ServicioController;
use App\Http\Controllers\Api\V1\TarifaController;
use App\Http\Controllers\Api\V1\VisitanteController;
use Illuminate\Support\Facades\Route;

// Persona B - CU3 (empresas, visitantes, servicios, tarifas, paquetes,
// proformas, campañas, cotización pública), pagos y búsqueda global.

// ----------------------------- Públicas (B3) -----------------------------
Route::get('publico/catalogo', [ProformaController::class, 'catalogoPublico']);
Route::post('publico/cotizar', [ProformaController::class, 'cotizarPublica'])->middleware('throttle:30,1');

// --------------------------- Con sesión ----------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // B6 - Búsqueda global
    Route::get('buscar', [BusquedaController::class, 'index']);

    // B1 - Empresas anunciantes
    Route::get('empresas', [EmpresaController::class, 'index'])->middleware('permiso:empresas.ver');
    Route::get('empresas/{empresa}', [EmpresaController::class, 'show'])->middleware('permiso:empresas.ver');
    Route::post('empresas', [EmpresaController::class, 'store'])->middleware('permiso:empresas.crear');
    Route::put('empresas/{empresa}', [EmpresaController::class, 'update'])->middleware('permiso:empresas.editar');
    Route::patch('empresas/{empresa}/estado', [EmpresaController::class, 'estado'])->middleware('permiso:empresas.eliminar');

    // B1 / B3 - Visitantes
    Route::get('visitantes', [VisitanteController::class, 'index'])->middleware('permiso:visitantes.ver');
    Route::get('visitantes/{visitante}', [VisitanteController::class, 'show'])->middleware('permiso:visitantes.ver');

    // B1 - Servicios publicitarios
    Route::get('servicios', [ServicioController::class, 'index'])->middleware('permiso:servicios.ver');
    Route::get('servicios/{servicio}', [ServicioController::class, 'show'])->middleware('permiso:servicios.ver');
    Route::post('servicios', [ServicioController::class, 'store'])->middleware('permiso:servicios.crear');
    Route::put('servicios/{servicio}', [ServicioController::class, 'update'])->middleware('permiso:servicios.editar');
    Route::patch('servicios/{servicio}/estado', [ServicioController::class, 'estado'])->middleware('permiso:servicios.eliminar');

    // B1 - Tarifas
    Route::get('tarifas', [TarifaController::class, 'index'])->middleware('permiso:tarifas.ver');
    Route::get('tarifas/{tarifa}', [TarifaController::class, 'show'])->middleware('permiso:tarifas.ver');
    Route::post('tarifas', [TarifaController::class, 'store'])->middleware('permiso:tarifas.crear');
    Route::put('tarifas/{tarifa}', [TarifaController::class, 'update'])->middleware('permiso:tarifas.editar');
    Route::patch('tarifas/{tarifa}/estado', [TarifaController::class, 'estado'])->middleware('permiso:tarifas.eliminar');

    // B1 - Paquetes
    Route::get('paquetes', [PaqueteController::class, 'index'])->middleware('permiso:paquetes.ver');
    Route::get('paquetes/{paquete}', [PaqueteController::class, 'show'])->middleware('permiso:paquetes.ver');
    Route::post('paquetes', [PaqueteController::class, 'store'])->middleware('permiso:paquetes.crear');
    Route::put('paquetes/{paquete}', [PaqueteController::class, 'update'])->middleware('permiso:paquetes.editar');
    Route::patch('paquetes/{paquete}/estado', [PaqueteController::class, 'estado'])->middleware('permiso:paquetes.eliminar');

    // B2 - Proformas
    Route::get('proformas', [ProformaController::class, 'index'])->middleware('permiso:proformas.ver');
    Route::get('proformas/{proforma}', [ProformaController::class, 'show'])->middleware('permiso:proformas.ver');
    Route::post('proformas', [ProformaController::class, 'store'])->middleware('permiso:proformas.crear');
    Route::put('proformas/{proforma}', [ProformaController::class, 'update'])->middleware('permiso:proformas.editar');
    Route::patch('proformas/{proforma}/estado', [ProformaController::class, 'estado'])->middleware('permiso:proformas.aprobar');
    Route::delete('proformas/{proforma}', [ProformaController::class, 'destroy'])->middleware('permiso:proformas.eliminar');

    // B2 - Campañas
    Route::get('campanias', [CampaniaController::class, 'index'])->middleware('permiso:campanias.ver');
    Route::get('campanias/{campania}', [CampaniaController::class, 'show'])->middleware('permiso:campanias.ver');
    Route::post('campanias', [CampaniaController::class, 'store'])->middleware('permiso:campanias.crear');
    Route::put('campanias/{campania}', [CampaniaController::class, 'update'])->middleware('permiso:campanias.editar');

    // B5 - Métodos de pago
    Route::get('metodos-pago', [MetodoPagoController::class, 'index'])->middleware('permiso:metodos_pago.ver');
    Route::post('metodos-pago', [MetodoPagoController::class, 'store'])->middleware('permiso:metodos_pago.crear');
    Route::put('metodos-pago/{metodo}', [MetodoPagoController::class, 'update'])->middleware('permiso:metodos_pago.editar');
    Route::patch('metodos-pago/{metodo}/estado', [MetodoPagoController::class, 'estado'])->middleware('permiso:metodos_pago.eliminar');

    // B5 - Pagos (historial, pago único y pago de cuota)
    Route::get('pagos', [PagoController::class, 'index'])->middleware('permiso:pagos.ver');
    Route::post('pagos', [PagoController::class, 'pagarProforma'])->middleware('permiso:pagos.crear');
    Route::post('cuotas/{cuota}/pagar', [PagoController::class, 'pagarCuota'])->middleware('permiso:pagos.crear');

    // B5 - Planes de pago en cuotas
    Route::get('planes-pago', [PlanPagoController::class, 'index'])->middleware('permiso:planes_pago.ver');
    Route::get('planes-pago/{plan}', [PlanPagoController::class, 'show'])->middleware('permiso:planes_pago.ver');
    Route::post('planes-pago', [PlanPagoController::class, 'store'])->middleware('permiso:planes_pago.crear');
});
