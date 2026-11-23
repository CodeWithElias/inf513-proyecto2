<?php

use Illuminate\Support\Facades\Route;

// Persona C - CU4 (material y revisiones), CU5 (espacios, pautas, notificaciones),
// CU7 (métricas y análisis) y estadísticas del dashboard.
//
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('materiales', [MaterialController::class, 'index'])->middleware('permiso:materiales.ver');
// });
//
// Códigos de privilegio disponibles: materiales.*, revisiones.*, espacios.*,
// pautas.*, notificaciones.ver|editar, metricas.ver|crear, analisis.ver,
// reportes.ver, dashboard.ver. Las estadísticas de accesos ya existen en
// GET /api/v1/bitacora/resumen (permiso bitacora.ver o dashboard.ver).
