<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API RESTful  -  prefijo /api/v1
|--------------------------------------------------------------------------
| Cada integrante trabaja en su propio archivo para evitar conflictos:
|   routes/api/seguridad.php  -> Persona A (auth, RBAC, bitácora, visitas, CU1, CU2, CU6)
|   routes/api/comercial.php  -> Persona B (CU3, pagos, búsqueda)
|   routes/api/operacion.php  -> Persona C (CU4, CU5, CU7, estadísticas)
|
| Middlewares disponibles:
|   auth:sanctum            -> requiere token (Authorization: Bearer ...)
|   permiso:recurso.accion  -> matriz de acceso (403 si el rol no lo tiene)
| Toda petición queda registrada en la bitácora (RegistrarBitacora).
*/

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/seguridad.php';
    require __DIR__.'/api/comercial.php';
    require __DIR__.'/api/operacion.php';
});
