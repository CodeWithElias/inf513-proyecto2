# Despliegue en tecnoweb.org.bo

URL final: `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2`

Laravel debe servirse desde su carpeta `public/`. Como la URL es un subdirectorio, lo más seguro es dejar el código
de Laravel **fuera** de la carpeta web y copiar a la carpeta web solo el contenido de `public/`.

## 0. Verificar el servidor (hacerlo primero)

```bash
php -v                       # Laravel 12 necesita PHP 8.2 o superior
php -m | grep -i -E "pdo_pgsql|mbstring|openssl|tokenizer|xml|ctype|fileinfo"
which composer               # si no hay, se sube la carpeta vendor/ ya instalada
```

Anotar aquí lo encontrado: PHP ____ · pdo_pgsql ____ · composer ____ · acceso SSH ____ · carpeta web ________

## 1. Preparar el paquete en la PC

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build       # genera public/build (no se sube a Git; se sube al servidor)
```

## 2. Subir al servidor

Estructura recomendada (ajustar `~/` a la carpeta del grupo):

```
~/proyecto2_app/                 ← TODO el proyecto Laravel (app, bootstrap, config, vendor, .env, storage, ...)
<carpeta web>/proyecto2/         ← SOLO el contenido de public/ (index.php, .htaccess, build/, favicon.ico, robots.txt)
```

En `<carpeta web>/proyecto2/index.php`, cambiar las dos rutas para que apunten a la app:

```php
if (file_exists($maintenance = __DIR__.'/../../../proyecto2_app/storage/framework/maintenance.php')) { require $maintenance; }
require __DIR__.'/../../../proyecto2_app/vendor/autoload.php';
$app = require_once __DIR__.'/../../../proyecto2_app/bootstrap/app.php';
```
(La cantidad de `../` depende de dónde quede la carpeta web respecto de `proyecto2_app`.)

**Si no se puede escribir fuera de la carpeta web:** subir la app a `<carpeta web>/proyecto2/_app/`, poner en
`_app/.htaccess` la línea `Require all denied` (para que nadie pueda leer `.env`) y usar `__DIR__.'/_app/...'` en `index.php`.

En `<carpeta web>/proyecto2/.htaccess` (el de `public/`), agregar después de `RewriteEngine On`:

```apache
RewriteBase /inf513/grupo13sa/proyecto2/
```

## 3. Configurar `.env` en el servidor

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2
DB_CONNECTION=pgsql
DB_HOST=db.tecnoweb.org.bo
DB_DATABASE=db_grupo13sa
DB_USERNAME=grupo13sa
DB_PASSWORD=********
```

## 4. Comandos finales (en `~/proyecto2_app`)

```bash
php artisan key:generate          # solo la primera vez
php artisan migrate --force       # idempotente: crea las tablas de seguridad si faltan
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

> **Nunca** ejecutar `migrate:fresh` ni `migrate:reset`: la BD es compartida con el Proyecto 1.

## 5. Comprobación

- `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2` muestra el login con estilos (si no hay estilos, revisar `APP_URL` y que se haya subido `build/`).
- `.../proyecto2/api/v1/auth/me` sin token responde `401` en JSON (si responde 404, revisar `RewriteBase`).
- Entrar con el Director y recargar una ruta interna (ej. `/usuarios`): debe abrir la pantalla (si da 404, revisar `.htaccess`).
- El pie de página muestra el contador de visitas.
