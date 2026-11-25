# Despliegue en tecnoweb (por FTP)

URL final: `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2`

## Cómo es el servidor

- Apache sobre Fedora. Solo hay acceso **FTP**: no se puede ejecutar `php artisan` ni `composer` en el servidor.
- La raíz del FTP es la carpeta del grupo (`/` → `.../inf513/grupo13sa/`). El proyecto va en **`/proyecto2`**.
- **Todo lo que está en el FTP es público en la web.** Por eso:
  - el código de Laravel va en `/proyecto2/laravel/`, bloqueado con `.htaccess`;
  - en `/proyecto2` solo queda lo público (`index.php`, `.htaccess`, `build/`, `img/`);
  - **no hay `.env` en el servidor**: la configuración está en `laravel/entorno.php`. Al ser `.php`, si alguien lo pide
    por la web se ejecuta y no muestra nada (un `.env` sí se podría descargar).

```
/proyecto2
├── index.php            ← entrada (carga laravel/entorno.php y laravel/bootstrap/app.php)
├── .htaccess            ← rewrite a index.php, RewriteBase, bloquea laravel/ y archivos ocultos
├── build/  img/  favicon.ico  robots.txt
├── diagnostico.php      ← TEMPORAL: borrar después de usarlo
└── laravel/             ← bloqueado (.htaccess "Require all denied")
    ├── app/ bootstrap/ config/ database/ lang/ resources/views/ routes/ storage/ vendor/
    └── entorno.php      ← configuración de producción (contraseña de la BD)
```

## Forma principal: GitHub Actions (automático)

Cada **push a `main`** ejecuta `.github/workflows/desplegar.yml`: arma el paquete con el mismo script y lo sube por FTP
a `/proyecto2`. Solo sube los archivos que cambiaron. También se puede lanzar a mano en *Actions → Desplegar en
tecnoweb → Run workflow*.

### Configuración (una sola vez)

1. En GitHub: *Settings → Secrets and variables → Actions → New repository secret*. Crear:

   | Secret | Valor |
   |---|---|
   | `FTP_SERVIDOR` | host del FTP (el que se usa en FileZilla) |
   | `FTP_USUARIO` / `FTP_CONTRASENA` | credenciales FTP del grupo |
   | `DB_HOST` | `db.tecnoweb.org.bo` |
   | `DB_PORT` | `5432` |
   | `DB_DATABASE` / `DB_USERNAME` | `db_grupo13sa` / `grupo13sa` |
   | `DB_PASSWORD` | contraseña de la BD |
   | `APP_KEY_PRODUCCION` | la salida de `php artisan key:generate --show` (generarla una vez y no cambiarla) |

2. Hacer el primer despliegue (push o merge a `main`). Tarda más la primera vez porque sube `vendor/` (~6 500 archivos).
3. En FileZilla, dar permisos `777` recursivos a `laravel/storage` y `laravel/bootstrap/cache` (la acción no puede
   cambiar permisos).
4. Verificar con el diagnóstico: en la PC ejecutar `node scripts/preparar-ftp.mjs`, subir **solo**
   `dist/ftp/proyecto2/diagnostico.php` a `/proyecto2` con FileZilla, abrir la URL que muestra el script y después
   **borrarlo** del servidor. El diagnóstico nunca se genera en Actions porque su clave quedaría en los logs.

> Si la acción falla al conectarse, es posible que el FTP de la facultad no acepte conexiones desde los servidores de
> GitHub o que no admita el modo pasivo. En ese caso, usar la forma manual de abajo.

## Forma manual (FileZilla)

### 1. Armar el paquete (en la PC)

Requisitos: el `.env` local con los datos de la BD, Node y Composer. **Detenga `npm run dev` antes.**

```bash
node scripts/preparar-ftp.mjs
# si composer no está en el PATH:
node scripts/preparar-ftp.mjs --composer "php C:/Users/<usuario>/bin/composer.phar"
```

El script:
1. compila el frontend (`npm run build`);
2. copia solo lo necesario (sin `node_modules`, `resources/js`, `tests`, `.git`, `public/hot`, logs ni caché local);
3. instala `vendor/` **sin** dependencias de desarrollo;
4. genera `index.php`, los `.htaccess`, `laravel/entorno.php` (con `APP_DEBUG=false`) y `diagnostico.php`;
5. muestra la URL del diagnóstico con una clave aleatoria.

Resultado: `dist/ftp/proyecto2/` (≈ 6 500 archivos, 27 MB). La carpeta `dist/` está en `.gitignore` porque contiene la
contraseña de la BD: **no subirla a GitHub**.

Las plantillas están en `deploy/ftp/` (esas sí van a Git).

### 2. Subir con FileZilla

1. Conectarse al FTP del grupo y entrar a `/proyecto2`.
2. Activar *Servidor → Forzar mostrar archivos ocultos* (para subir los `.htaccess`).
3. Arrastrar el **contenido** de `dist/ftp/proyecto2/` (no la carpeta en sí) a `/proyecto2`.
4. Dar permisos de escritura a Apache (clic derecho → *Permisos de archivo…* → `777`, marcar *Recursivo en
   subdirectorios*) en:
   - `laravel/storage`
   - `laravel/bootstrap/cache`

   Si el servidor no deja escribir ahí (por ejemplo por SELinux), `entorno.php` usa automáticamente la carpeta temporal
   del sistema; el diagnóstico avisa cuál se está usando.

### 3. Verificar

1. Abrir la URL del diagnóstico que mostró el script:
   `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2/diagnostico.php?clave=...`
   Revisa PHP ≥ 8.2, `pdo_pgsql` y demás extensiones, permisos de escritura, conexión a la BD, que `laravel/` esté
   bloqueada y que el rewrite funcione.
2. **Borrar `diagnostico.php` del servidor.**
3. Abrir `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2`: debe verse la portada pública. Iniciar sesión
   (contraseña `clave123!`) y recorrer algunas pantallas.

### 4. Actualizar después de un cambio

Volver a correr `node scripts/preparar-ftp.mjs` (reutiliza la misma `APP_KEY`) y subir **solo lo que cambió**. Lo
normal es `build/`, `laravel/app`, `laravel/routes`, `laravel/resources/views` y `laravel/database`. FileZilla puede
sobrescribir solo los archivos más nuevos.

## Base de datos

Las migraciones (`seguridad` y `pagos`) **ya están aplicadas** en `db_grupo13sa` y los datos de prueba de pagos ya están
cargados. En el servidor no hace falta (ni se puede) ejecutar `migrate`. Si en el futuro hay una migración nueva, se
aplica desde una PC con `php artisan migrate`, porque la BD es la misma.

> **Nunca** ejecutar `migrate:fresh` ni `migrate:reset`: la BD es compartida con el Proyecto 1.

## Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| Página en blanco, la consola del navegador pide `localhost:5173` | Se subió `public/hot` | Borrar `hot` del servidor y regenerar el paquete con `npm run dev` detenido |
| La portada carga sin estilos | Falta `build/` o `APP_URL` mal | Subir `build/`; revisar `APP_URL` en `laravel/entorno.php` |
| `/proyecto2/usuarios` o `/api/v1/...` da 404 de Apache | `.htaccess` no subido o `RewriteBase` distinto | Subir `.htaccess`; el diagnóstico muestra el `SCRIPT_NAME` real |
| Error 500 | Permisos de escritura o extensión faltante | Ver el diagnóstico |
| El diagnóstico dice "laravel/composer.json NO bloqueado" | Apache ignora los `.htaccess` | **Borrar la carpeta `laravel/` de inmediato** y pedir al administrador que habilite `AllowOverride` |
