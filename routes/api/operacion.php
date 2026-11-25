<?php

use App\Http\Controllers\Api\V1\MaterialController;
use Illuminate\Support\Facades\Route;

// Persona C - CU4 (material y revisiones), CU5 (espacios, pautas, notificaciones),
// CU7 (métricas y análisis) y estadísticas del dashboard.

Route::middleware('auth:sanctum')->group(function () {
    // CU4: Material publicitario y revisiones
    Route::get('materiales', [MaterialController::class, 'index'])->middleware('permiso:materiales.ver');
    Route::get('materiales/{material}', [MaterialController::class, 'show'])->middleware('permiso:materiales.ver');
    Route::post('materiales', [MaterialController::class, 'store'])->middleware('permiso:materiales.crear');
    Route::put('materiales/{material}', [MaterialController::class, 'update'])->middleware('permiso:materiales.editar');
    Route::post('materiales/{material}/version', [MaterialController::class, 'nuevaVersion'])->middleware('permiso:materiales.crear');
    Route::delete('materiales/{material}', [MaterialController::class, 'destroy'])->middleware('permiso:materiales.eliminar');

    Route::post('materiales/{material}/revisar', [MaterialController::class, 'revisar'])->middleware('permiso:revisiones.crear');
    Route::get('materiales/{material}/revisiones', [MaterialController::class, 'revisiones'])->middleware('permiso:revisiones.ver');
});
