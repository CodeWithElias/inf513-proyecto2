<?php

/*
| Diagnóstico del servidor para el despliegue por FTP (Grupo 13 SA).
| Uso: __URL__/diagnostico.php?clave=__CLAVE__
| BORRAR ESTE ARCHIVO DEL SERVIDOR DESPUÉS DE USARLO.
*/

if (($_GET['clave'] ?? '') !== '__CLAVE__') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

$filas = [];
$fila = function (string $grupo, string $prueba, ?bool $ok, string $detalle = '') use (&$filas) {
    $filas[] = [$grupo, $prueba, $ok, $detalle];
};

// ---------------------------------------------------------------- PHP
$fila('PHP', 'Versión de PHP (Laravel 12 necesita 8.2 o superior)', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION.' · '.PHP_SAPI);
foreach (['pdo_pgsql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'filter', 'session', 'dom', 'json', 'pdo'] as $ext) {
    $fila('PHP', "Extensión {$ext}", extension_loaded($ext));
}
$fila('PHP', 'Extensión curl (opcional, para estas pruebas)', extension_loaded('curl') ? true : null);

// ---------------------------------------------------------------- Servidor y rutas
$base = '__BASE__';
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$fila('Servidor', 'Software', null, $_SERVER['SERVER_SOFTWARE'] ?? '(desconocido)');
$fila('Servidor', "La URL base coincide con la configurada ({$base})", dirname($script) === $base, "SCRIPT_NAME = {$script}");
$fila('Servidor', 'Carpeta física de /proyecto2', null, __DIR__);

// ---------------------------------------------------------------- Archivos
$laravel = __DIR__.'/laravel';
foreach (['laravel/vendor/autoload.php', 'laravel/bootstrap/app.php', 'laravel/entorno.php', 'laravel/.htaccess', '.htaccess', 'build/manifest.json'] as $f) {
    $fila('Archivos', "Existe {$f}", file_exists(__DIR__.'/'.$f));
}
$fila('Archivos', 'NO existe build/../hot (archivo de desarrollo)', ! file_exists(__DIR__.'/hot'));

// ---------------------------------------------------------------- Escritura
$escribe = function (string $dir): bool {
    if (! is_dir($dir)) {
        return false;
    }
    $p = $dir.'/.diag_'.bin2hex(random_bytes(4));

    return @file_put_contents($p, 'x') !== false && @unlink($p);
};
$storageOk = true;
foreach (['storage/framework/views', 'storage/framework/cache/data', 'storage/logs', 'bootstrap/cache'] as $d) {
    $ok = $escribe("{$laravel}/{$d}");
    $storageOk = $storageOk && ($d === 'bootstrap/cache' || $ok);
    $fila('Escritura', "Apache puede escribir en laravel/{$d}", $ok);
}
$tmp = rtrim(sys_get_temp_dir(), '/\\');
$fila('Escritura', 'Respaldo: carpeta temporal del sistema escribible', $escribe($tmp), $tmp.($storageOk ? ' (no hace falta)' : ' (se usará automáticamente)'));

// ---------------------------------------------------------------- Base de datos
if (file_exists("{$laravel}/entorno.php")) {
    require "{$laravel}/entorno.php";
    try {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_DATABASE']);
        $t = microtime(true);
        $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $usuarios = $pdo->query('select count(*) from usuario')->fetchColumn();
        $tablas = $pdo->query("select count(*) from information_schema.tables where table_schema='public' and table_name in ('privilegio','menu_item','bitacora','metodo_pago','transaccion_pago')")->fetchColumn();
        $ms = round((microtime(true) - $t) * 1000);
        $fila('Base de datos', 'Conexión a PostgreSQL', true, "{$_ENV['DB_HOST']} · {$usuarios} usuarios · {$ms} ms");
        $fila('Base de datos', 'Tablas del Proyecto 2 presentes (5 de 5)', (int) $tablas === 5, "{$tablas} de 5");
    } catch (Throwable $e) {
        $fila('Base de datos', 'Conexión a PostgreSQL', false, preg_replace('/password=\S+/i', 'password=***', $e->getMessage()));
    }
}

// ---------------------------------------------------------------- Pruebas por HTTP (necesitan curl)
$https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$raiz = $https.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').$base;
$pedir = function (string $url, array $cabeceras = []): array {
    if (! function_exists('curl_init')) {
        return [null, ''];
    }
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_HTTPHEADER => $cabeceras, CURLOPT_FOLLOWLOCATION => false]);
    $cuerpo = (string) curl_exec($c);
    $codigo = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);

    return [$codigo, $cuerpo];
};
if (function_exists('curl_init')) {
    [$c1] = $pedir("{$raiz}/laravel/composer.json");
    $fila('Seguridad', 'laravel/composer.json bloqueado desde la web', in_array($c1, [403, 404], true), "HTTP {$c1}".(in_array($c1, [403, 404], true) ? '' : ' ⚠ ¡EL .htaccess NO SE APLICA! Borre la carpeta laravel/ y avise.'));
    [$c2, $b2] = $pedir("{$raiz}/laravel/entorno.php");
    $fila('Seguridad', 'laravel/entorno.php no muestra contenido', $c2 !== 200 || trim($b2) === '', "HTTP {$c2}");
    [$c3, $b3] = $pedir("{$raiz}/ruta-de-prueba-react");
    $fila('Rewrite', 'Rutas de React llegan a Laravel (mod_rewrite + .htaccess)', $c3 === 200 && str_contains($b3, 'id="app"'), "HTTP {$c3}");
    [$c4, $b4] = $pedir("{$raiz}/api/v1/auth/me", ['Accept: application/json']);
    $fila('Rewrite', 'API responde 401 en JSON sin sesión', $c4 === 401 && str_contains($b4, 'message'), "HTTP {$c4}: ".mb_substr(strip_tags($b4), 0, 120));
}

// ---------------------------------------------------------------- Salida
$errores = count(array_filter($filas, fn ($f) => $f[2] === false));
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnóstico · Proyecto 2</title>
<style>
body{font-family:system-ui,Segoe UI,sans-serif;max-width:980px;margin:24px auto;padding:0 16px;color:#141b2e;background:#f3f5fa}
h1{color:#0a35a8}table{width:100%;border-collapse:collapse;background:#fff}td,th{border:1px solid #d3d9e6;padding:6px 10px;text-align:left;font-size:14px;vertical-align:top}
.ok{color:#1e7b45;font-weight:700}.no{color:#c01818;font-weight:700}.na{color:#545e75}.aviso{background:#fff3cd;border:1px solid #e0b400;padding:10px;margin:16px 0}
</style></head><body>
<h1>Diagnóstico del servidor · Proyecto 2</h1>
<p><strong><?= $errores === 0 ? '✅ Todo correcto.' : "❌ {$errores} prueba(s) fallaron." ?></strong></p>
<div class="aviso">⚠ <strong>Borre <code>diagnostico.php</code> del servidor</strong> cuando termine.</div>
<table><tr><th>Grupo</th><th>Prueba</th><th>Resultado</th><th>Detalle</th></tr>
<?php foreach ($filas as [$g, $p, $ok, $d]): ?>
<tr><td><?= htmlspecialchars($g) ?></td><td><?= htmlspecialchars($p) ?></td>
<td class="<?= $ok === true ? 'ok' : ($ok === false ? 'no' : 'na') ?>"><?= $ok === true ? '✔ Sí' : ($ok === false ? '✘ No' : '—') ?></td>
<td><?= htmlspecialchars($d) ?></td></tr>
<?php endforeach; ?>
</table></body></html>
