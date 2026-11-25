#!/usr/bin/env node
/**
 * Arma el paquete para subir por FTP a tecnoweb (carpeta /proyecto2).
 *
 * Uso (desde la raíz del proyecto):
 *   node scripts/preparar-ftp.mjs
 *   node scripts/preparar-ftp.mjs --composer "php C:/Users/<usuario>/bin/composer.phar"
 *   node scripts/preparar-ftp.mjs --url http://127.0.0.1:8010/proyecto2      (prueba local)
 *
 * Resultado: dist/ftp/proyecto2/  ->  subir su CONTENIDO a /proyecto2 por FTP.
 *   proyecto2/
 *     index.php, .htaccess, build/, img/, favicon.ico, robots.txt   (público)
 *     diagnostico.php                                              (temporal: borrar después)
 *     laravel/                                                     (bloqueado por .htaccess)
 *       app, bootstrap, config, database, lang, resources/views, routes, storage, vendor, entorno.php
 */
import { execSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const raiz = process.cwd();
const arg = (nombre, defecto) => {
    const i = process.argv.indexOf(`--${nombre}`);
    return i >= 0 ? process.argv[i + 1] : defecto;
};
const bandera = (nombre) => process.argv.includes(`--${nombre}`);

const URL_APP = arg('url', 'https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2').replace(/\/+$/, '');
const BASE = new URL(URL_APP).pathname.replace(/\/+$/, '');
const COMPOSER = arg('composer', process.env.COMPOSER_CMD ?? 'composer');
const SALIDA = path.join(raiz, arg('salida', 'dist/ftp'));
const DESTINO = path.join(SALIDA, 'proyecto2');
const LARAVEL = path.join(DESTINO, 'laravel');
const PLANTILLAS = path.join(raiz, 'deploy', 'ftp');

const paso = (t) => console.log(`\n▶ ${t}`);
const correr = (cmd, cwd = raiz) => execSync(cmd, { cwd, stdio: 'inherit', shell: true, env: { ...process.env, APP_ENV: 'production' } });

if (!fs.existsSync(path.join(raiz, 'artisan'))) {
    console.error('Ejecute este script desde la raíz del proyecto (donde está "artisan").');
    process.exit(1);
}

// ---------------------------------------------------------------- 1. Datos de la BD
// En GitHub Actions vienen de los Secrets (variables de entorno); en la PC, del .env local.
const leerEnv = (archivo) =>
    !fs.existsSync(archivo)
        ? {}
        : Object.fromEntries(
              fs.readFileSync(archivo, 'utf8').split(/\r?\n/)
                  .filter((l) => /^[A-Z0-9_]+=/.test(l))
                  .map((l) => [l.slice(0, l.indexOf('=')), l.slice(l.indexOf('=') + 1).replace(/^"(.*)"$/, '$1')]),
          );
const envLocal = leerEnv(path.join(raiz, '.env'));
const local = {};
// En el servidor, PostgreSQL está en la misma máquina: db.tecnoweb.org.bo (IP pública) rechaza la
// conexión desde adentro, así que el host de producción es 127.0.0.1 (nunca el del .env local).
envLocal.DB_HOST = '127.0.0.1';
for (const k of ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']) {
    local[k] = process.env[k] || envLocal[k];
    if (!local[k]) {
        console.error(`Falta ${k}: defínalo en el .env local o como variable de entorno (Secret en GitHub Actions).`);
        process.exit(1);
    }
}

// APP_KEY estable: 1) variable de entorno (Secret en Actions), 2) la de un paquete anterior, 3) una nueva.
let appKey = process.env.APP_KEY_PRODUCCION || null;
const entornoAnterior = path.join(LARAVEL, 'entorno.php');
if (!appKey && fs.existsSync(entornoAnterior)) {
    appKey = fs.readFileSync(entornoAnterior, 'utf8').match(/'APP_KEY' => '([^']+)'/)?.[1] ?? null;
}
if (!appKey && process.env.CI) {
    console.error('Falta el Secret APP_KEY_PRODUCCION: sin él la clave cambiaría en cada despliegue.');
    process.exit(1);
}
appKey ??= `base64:${randomBytes(32).toString('base64')}`;

// ---------------------------------------------------------------- 2. Compilar el frontend
if (!bandera('sin-build')) {
    paso('Compilando el frontend (npm run build)');
    correr('npm run build');
}
if (!fs.existsSync(path.join(raiz, 'public', 'build', 'manifest.json'))) {
    console.error('No existe public/build/manifest.json. Ejecute "npm run build".');
    process.exit(1);
}

// ---------------------------------------------------------------- 3. Copiar archivos
paso(`Preparando ${path.relative(raiz, DESTINO)}`);
fs.rmSync(DESTINO, { recursive: true, force: true });
fs.mkdirSync(LARAVEL, { recursive: true });

const copiar = (origen, destino) => fs.cpSync(path.join(raiz, origen), destino, { recursive: true });
for (const dir of ['app', 'bootstrap', 'config', 'database', 'lang', 'routes', 'storage']) {
    copiar(dir, path.join(LARAVEL, dir));
}
copiar('resources/views', path.join(LARAVEL, 'resources', 'views'));
for (const f of ['artisan', 'composer.json', 'composer.lock']) {
    copiar(f, path.join(LARAVEL, f));
}

// storage y bootstrap/cache: solo la estructura de carpetas (sin logs, vistas compiladas ni caché local)
const limpiar = (dir, conservar = ['.gitignore']) => {
    if (!fs.existsSync(dir)) return;
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) limpiar(p, conservar);
        else if (!conservar.includes(e.name)) fs.rmSync(p, { force: true });
    }
};
limpiar(path.join(LARAVEL, 'storage'));
limpiar(path.join(LARAVEL, 'bootstrap', 'cache'));

// Un archivo .keep en cada carpeta para que el FTP cree también las carpetas vacías
// (la acción de GitHub ignora los .gitignore).
const marcar = (dir) => {
    fs.writeFileSync(path.join(dir, '.keep'), '');
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        if (e.isDirectory()) marcar(path.join(dir, e.name));
    }
};
marcar(path.join(LARAVEL, 'storage'));
marcar(path.join(LARAVEL, 'bootstrap', 'cache'));

// Archivos públicos (sin public/hot ni public/index.php)
for (const e of fs.readdirSync(path.join(raiz, 'public'))) {
    if (['hot', 'index.php', '.htaccess', 'storage'].includes(e)) continue;
    copiar(path.join('public', e), path.join(DESTINO, e));
}

// ---------------------------------------------------------------- 4. vendor sin dependencias de desarrollo
paso('Instalando dependencias de producción (composer install --no-dev)');
correr(`${COMPOSER} install --no-dev --optimize-autoloader --no-interaction --no-progress`, LARAVEL);

// ---------------------------------------------------------------- 5. Archivos del despliegue
paso('Generando index.php, .htaccess, entorno.php y diagnostico.php');
const plantilla = (nombre) => fs.readFileSync(path.join(PLANTILLAS, nombre), 'utf8');

fs.writeFileSync(path.join(DESTINO, 'index.php'), plantilla('index.php'));
fs.writeFileSync(path.join(DESTINO, '.htaccess'), plantilla('.htaccess').replaceAll('__BASE__', BASE));
fs.writeFileSync(path.join(LARAVEL, '.htaccess'), plantilla('laravel.htaccess'));

const variables = {
    APP_NAME: 'TVU Canal 11 - Gestion Publicitaria',
    APP_ENV: 'production',
    APP_KEY: appKey,
    APP_DEBUG: 'false',
    APP_URL: URL_APP,
    APP_TIMEZONE: 'America/La_Paz',
    APP_LOCALE: 'es',
    APP_FALLBACK_LOCALE: 'es',
    APP_MAINTENANCE_DRIVER: 'file',
    DB_CONNECTION: 'pgsql',
    DB_HOST: local.DB_HOST,
    DB_PORT: local.DB_PORT,
    DB_DATABASE: local.DB_DATABASE,
    DB_USERNAME: local.DB_USERNAME,
    DB_PASSWORD: local.DB_PASSWORD,
    DB_PERSISTENT: 'false',
    BCRYPT_ROUNDS: '12',
    SANCTUM_EXPIRATION: '480',
    SESSION_DRIVER: 'array',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    LOG_CHANNEL: 'single',
    LOG_LEVEL: 'error',
};
const php = (v) => `'${String(v).replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
const arreglo = `[\n${Object.entries(variables).map(([k, v]) => `    '${k}' => ${php(v)},`).join('\n')}\n]`;
fs.writeFileSync(path.join(LARAVEL, 'entorno.php'), plantilla('entorno.plantilla.php').replace('__VARIABLES__', arreglo));

// El diagnóstico se genera solo en la PC (en Actions su clave quedaría en los logs).
const conDiagnostico = !bandera('sin-diagnostico') && !process.env.CI;
const clave = randomBytes(12).toString('hex');
if (conDiagnostico) {
    fs.writeFileSync(
        path.join(DESTINO, 'diagnostico.php'),
        plantilla('diagnostico.php').replaceAll('__CLAVE__', clave).replaceAll('__BASE__', BASE).replaceAll('__URL__', URL_APP),
    );
}

// ---------------------------------------------------------------- 6. Resumen
let archivos = 0;
let bytes = 0;
const contar = (dir) => {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) contar(p);
        else {
            archivos++;
            bytes += fs.statSync(p).size;
        }
    }
};
contar(DESTINO);

if (!conDiagnostico) {
    console.log(`\n✅ Paquete listo: ${path.relative(raiz, DESTINO)}  (${archivos} archivos, ${(bytes / 1048576).toFixed(1)} MB)`);
    process.exit(0);
}
console.log(`
✅ Paquete listo: ${path.relative(raiz, DESTINO)}  (${archivos} archivos, ${(bytes / 1048576).toFixed(1)} MB)

Siguientes pasos:
  1. Con FileZilla, suba el CONTENIDO de ${path.relative(raiz, DESTINO)} a /proyecto2 del servidor
     (incluidos los archivos ocultos .htaccess).
  2. Abra el diagnóstico y verifique que todo esté en verde:
     ${URL_APP}/diagnostico.php?clave=${clave}
  3. BORRE diagnostico.php del servidor.
  4. Abra ${URL_APP}
`);
