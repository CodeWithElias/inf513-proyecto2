<?php

use Illuminate\Support\Facades\Route;

// Persona B - CU3 (empresas, servicios, tarifas, paquetes, proformas, campañas),
// pagos y búsqueda global. Ejemplo de cómo declarar una ruta protegida:
//
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('empresas', [EmpresaController::class, 'index'])->middleware('permiso:empresas.ver');
//     Route::post('empresas', [EmpresaController::class, 'store'])->middleware('permiso:empresas.crear');
// });
//
// Los códigos de privilegio ya existen en la BD (tabla privilegio): empresas.*,
// visitantes.ver, servicios.*, tarifas.*, paquetes.*, proformas.* (+ aprobar,
// descuento), campanias.*, metodos_pago.*, pagos.*, planes_pago.*
