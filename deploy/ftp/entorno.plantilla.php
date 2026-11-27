<?php

/*
|--------------------------------------------------------------------------
| Configuración de producción (reemplaza al .env en el servidor)
|--------------------------------------------------------------------------
| Generado por scripts/preparar-ftp.mjs. NO subir a Git.
| Es un archivo .php a propósito: si alguien lo pidiera por la web, PHP lo
| ejecuta y no muestra nada (un .env sí se podría descargar).
*/

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

$variables = __VARIABLES__;

foreach ($variables as $clave => $valor) {
    $_ENV[$clave] = $valor;
    $_SERVER[$clave] = $valor;
    if (function_exists('putenv')) {
        @putenv("{$clave}={$valor}");
    }
}

/*
| Carpeta storage escribible. Apache necesita escribir vistas compiladas, caché
| y registros. Si laravel/storage no se puede escribir (permisos o SELinux en
| Fedora), se usa la carpeta temporal del sistema.
*/
$storage = __DIR__.'/storage';
$escribible = static function (string $dir): bool {
    if (! is_dir($dir) || ! is_writable($dir)) {
        return false;
    }
    $prueba = $dir.'/.prueba_escritura';

    return @file_put_contents($prueba, '1') !== false && @unlink($prueba);
};

// Carpetas que el despliegue por FTP puede no crear (la acción excluye la caché y los materiales subidos):
// se crean aquí si faltan, antes de comprobar si se puede escribir.
foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private/materiales'] as $sub) {
    if (! is_dir("{$storage}/{$sub}") && is_writable($storage)) {
        @mkdir("{$storage}/{$sub}", 0777, true);
    }
}

if (! $escribible($storage.'/framework/views') || ! $escribible($storage.'/framework/cache/data') || ! $escribible($storage.'/logs')) {
    $alterno = rtrim(sys_get_temp_dir(), '/\\').'/grupo13sa-proyecto2-storage';
    foreach (['framework/cache/data', 'framework/views', 'framework/sessions', 'logs', 'app/public', 'app/private'] as $sub) {
        if (! is_dir("{$alterno}/{$sub}")) {
            @mkdir("{$alterno}/{$sub}", 0775, true);
        }
    }
    $_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $alterno;
}
