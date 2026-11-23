<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BitacoraController;
use App\Http\Controllers\Api\V1\CanalController;
use App\Http\Controllers\Api\V1\MonitoreoController;
use App\Http\Controllers\Api\V1\OpcionesController;
use App\Http\Controllers\Api\V1\RedSocialController;
use App\Http\Controllers\Api\V1\RepresentanteController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\UnidadController;
use App\Http\Controllers\Api\V1\UsuarioController;
use App\Http\Controllers\Api\V1\VisitaController;
use Illuminate\Support\Facades\Route;

// Persona A - Seguridad, RBAC, bitácora, visitas, CU1, CU2 y CU6

// ----------------------------- Públicas -----------------------------
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('visitas', [VisitaController::class, 'registrar'])->middleware('throttle:120,1');

// --------------------------- Con sesión -----------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('opciones/{tipo}', [OpcionesController::class, 'show']);

    // Bitácora
    Route::get('bitacora', [BitacoraController::class, 'index'])->middleware('permiso:bitacora.ver');
    Route::get('bitacora/resumen', [BitacoraController::class, 'resumen'])->middleware('permiso:bitacora.ver|dashboard.ver');

    // CU1 - Usuarios
    Route::get('usuarios', [UsuarioController::class, 'index'])->middleware('permiso:usuarios.ver');
    Route::get('usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware('permiso:usuarios.ver');
    Route::post('usuarios', [UsuarioController::class, 'store'])->middleware('permiso:usuarios.crear');
    Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('permiso:usuarios.editar');
    Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->middleware('permiso:usuarios.eliminar');

    // CU1 - Roles y matriz de privilegios
    Route::get('roles', [RolController::class, 'index'])->middleware('permiso:roles.ver');
    Route::get('roles/{rol}', [RolController::class, 'show'])->middleware('permiso:roles.ver');
    Route::post('roles', [RolController::class, 'store'])->middleware('permiso:roles.crear');
    Route::put('roles/{rol}', [RolController::class, 'update'])->middleware('permiso:roles.editar');
    Route::patch('roles/{rol}/estado', [RolController::class, 'estado'])->middleware('permiso:roles.eliminar');
    Route::get('roles/{rol}/privilegios', [RolController::class, 'privilegios'])->middleware('permiso:roles.ver');
    Route::put('roles/{rol}/privilegios', [RolController::class, 'asignar'])->middleware('permiso:roles.permisos');

    // CU1 - Unidades universitarias
    Route::get('unidades', [UnidadController::class, 'index'])->middleware('permiso:unidades.ver');
    Route::get('unidades/{unidad}', [UnidadController::class, 'show'])->middleware('permiso:unidades.ver');
    Route::post('unidades', [UnidadController::class, 'store'])->middleware('permiso:unidades.crear');
    Route::put('unidades/{unidad}', [UnidadController::class, 'update'])->middleware('permiso:unidades.editar');
    Route::patch('unidades/{unidad}/estado', [UnidadController::class, 'estado'])->middleware('permiso:unidades.eliminar');

    // CU1 - Clientes (representantes de empresas)
    Route::get('representantes', [RepresentanteController::class, 'index'])->middleware('permiso:representantes.ver');
    Route::get('representantes/{representante}', [RepresentanteController::class, 'show'])->middleware('permiso:representantes.ver');
    Route::post('representantes', [RepresentanteController::class, 'store'])->middleware('permiso:representantes.crear');
    Route::put('representantes/{representante}', [RepresentanteController::class, 'update'])->middleware('permiso:representantes.editar');
    Route::delete('representantes/{representante}', [RepresentanteController::class, 'destroy'])->middleware('permiso:representantes.eliminar');

    // CU2 - Plataformas y canales
    Route::get('plataformas', [CanalController::class, 'plataformas'])->middleware('permiso:plataformas.ver');
    Route::post('plataformas', [CanalController::class, 'crearPlataforma'])->middleware('permiso:plataformas.crear');
    Route::get('canales', [CanalController::class, 'index'])->middleware('permiso:canales.ver');
    Route::post('canales', [CanalController::class, 'store'])->middleware('permiso:canales.crear');
    Route::patch('canales/{canal}/estado', [CanalController::class, 'estado'])->middleware('permiso:canales.eliminar');

    // CU2 - Redes sociales institucionales y seguidores
    Route::get('redes', [RedSocialController::class, 'index'])->middleware('permiso:redes.ver');
    Route::get('redes/{red}', [RedSocialController::class, 'show'])->middleware('permiso:redes.ver');
    Route::post('redes', [RedSocialController::class, 'store'])->middleware('permiso:redes.crear');
    Route::put('redes/{red}', [RedSocialController::class, 'update'])->middleware('permiso:redes.editar');
    Route::patch('redes/{red}/estado', [RedSocialController::class, 'estado'])->middleware('permiso:redes.eliminar');
    Route::get('redes/{red}/seguidores', [RedSocialController::class, 'seguidores'])->middleware('permiso:seguidores.ver');
    Route::post('redes/{red}/seguidores', [RedSocialController::class, 'registrarSeguidores'])->middleware('permiso:seguidores.crear');

    // CU6 - Monitoreo
    Route::get('terminos', [MonitoreoController::class, 'terminos'])->middleware('permiso:terminos.ver');
    Route::post('terminos', [MonitoreoController::class, 'crearTermino'])->middleware('permiso:terminos.crear');
    Route::patch('terminos/{termino}/estado', [MonitoreoController::class, 'estadoTermino'])->middleware('permiso:terminos.eliminar');
    Route::get('paginas', [MonitoreoController::class, 'paginas'])->middleware('permiso:paginas.ver');
    Route::get('paginas/{pagina}', [MonitoreoController::class, 'pagina'])->middleware('permiso:paginas.ver');
    Route::post('paginas', [MonitoreoController::class, 'registrarPagina'])->middleware('permiso:paginas.crear');
    Route::patch('paginas/{pagina}/estado', [MonitoreoController::class, 'revisarPagina'])->middleware('permiso:paginas.editar');
    Route::get('ejecuciones', [MonitoreoController::class, 'ejecuciones'])->middleware('permiso:ejecuciones.ver');
});
