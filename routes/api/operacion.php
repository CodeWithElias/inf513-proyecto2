<?php

use App\Http\Controllers\Api\V1\EspacioController;
use App\Http\Controllers\Api\V1\EstadisticaController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\MetricaController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\PautaController;
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

    // CU5: Espacios publicitarios
    Route::get('espacios', [EspacioController::class, 'index'])->middleware('permiso:espacios.ver');
    Route::post('espacios', [EspacioController::class, 'store'])->middleware('permiso:espacios.crear');
    Route::put('espacios/{espacio}', [EspacioController::class, 'update'])->middleware('permiso:espacios.editar');
    Route::delete('espacios/{espacio}', [EspacioController::class, 'destroy'])->middleware('permiso:espacios.eliminar');

    // CU5: Pautas publicitarias
    Route::get('pautas', [PautaController::class, 'index'])->middleware('permiso:pautas.ver');
    Route::post('pautas', [PautaController::class, 'store'])->middleware('permiso:pautas.crear');
    Route::put('pautas/{pauta}/reprogramar', [PautaController::class, 'reprogramar'])->middleware('permiso:pautas.editar');
    Route::patch('pautas/{pauta}/emitir', [PautaController::class, 'emitir'])->middleware('permiso:pautas.editar');
    Route::delete('pautas/{pauta}', [PautaController::class, 'destroy'])->middleware('permiso:pautas.eliminar');

    // CU5: Notificaciones
    Route::get('notificaciones', [NotificacionController::class, 'index'])->middleware('permiso:notificaciones.ver');
    Route::patch('notificaciones/{notificacion}/leida', [NotificacionController::class, 'marcarLeida'])->middleware('permiso:notificaciones.editar');
    Route::post('notificaciones/marcar-todas', [NotificacionController::class, 'marcarTodasLeidas'])->middleware('permiso:notificaciones.editar');

    // CU7: Métricas y análisis de impacto
    Route::get('metricas', [MetricaController::class, 'index'])->middleware('permiso:metricas.ver');
    Route::post('metricas', [MetricaController::class, 'store'])->middleware('permiso:metricas.crear');
    Route::get('metricas/resumen', [MetricaController::class, 'resumen'])->middleware('permiso:metricas.ver');

    // C4: Dashboard de estadísticas de negocio
    Route::get('estadisticas/negocio', [EstadisticaController::class, 'negocio'])->middleware('permiso:dashboard.ver');
});
