<?php

/*
|--------------------------------------------------------------------------
| Punto de entrada en tecnoweb (despliegue por FTP)
|--------------------------------------------------------------------------
| En el servidor solo hay FTP y todo lo que se sube queda publicado en la web,
| por eso el código de Laravel va en la subcarpeta "laravel/" (bloqueada por
| .htaccess) y aquí, en /proyecto2, solo está lo público (index.php, build/, img/).
| La configuración no está en un .env: está en laravel/entorno.php.
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$laravel = __DIR__.'/laravel';

// Variables de entorno de producción (reemplaza al .env)
require $laravel.'/entorno.php';

if (file_exists($maintenance = $laravel.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravel.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravel.'/bootstrap/app.php';

// La carpeta pública es esta (/proyecto2), no laravel/public
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
