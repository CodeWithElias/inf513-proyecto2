# Sistema de Gestión Publicitaria — Canal 11 / UAGRM (Proyecto 2)

**Grupo 13 SA · INF513 Tecnología Web · UAGRM**

Versión web del sistema del Proyecto 1 (que funcionaba por correo). Arquitectura de 3 capas:

| Capa | Tecnología | Carpeta |
|---|---|---|
| 1. Presentación | React 19 + React Router + Tailwind CSS 4 + Axios (SPA) | `resources/js`, `resources/css` |
| 2. Lógica de negocio | Laravel 12 (API RESTful `/api/v1`, Sanctum, middlewares RBAC y bitácora) | `app/`, `routes/api*` |
| 3. Persistencia | PostgreSQL `db_grupo13sa` en `db.tecnoweb.org.bo` (tablas, triggers y funciones) | `database/sql` |

- Despliegue: `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2` (ver [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md))
- Plan de trabajo del equipo: [docs/PLAN_IMPLEMENTACION.md](docs/PLAN_IMPLEMENTACION.md)

---

## 1. Instalación local

Requisitos: PHP 8.2 o superior con `pdo_pgsql`, Composer 2, Node 20 o superior.

```bash
composer install
npm install
cp .env.example .env          # completar DB_PASSWORD con la clave del grupo
php artisan key:generate
php artisan migrate           # crea las tablas de seguridad (idempotente; no toca las del Proyecto 1)
npm run dev                   # terminal 1: Vite con recarga en caliente
php artisan serve             # terminal 2: http://localhost:8000
```

> **Windows:** si al activar `extension=pdo_pgsql` en `php.ini` aparece *"An Application Control policy has blocked
> this file"*, es Smart App Control o WDAC bloqueando la DLL. Hay dos salidas: desactivar Smart App Control
> (Configuración → Seguridad de Windows → Control de aplicaciones y navegador) o usar Docker (sección 6).

La base de datos es la **misma del Proyecto 1** (ya tiene las 27 tablas y sus datos). El Proyecto 2 solo **agrega** tablas.

### Usuarios de prueba

Contraseña de **todos** los usuarios: `clave123!`

| Rol | Correo |
|---|---|
| Administrador | los correos personales de los administradores del grupo (usuarios 1 y 2) |
| Director | `carlos.mendoza@example.com` |
| Encargado de Unidad | `patricia.justiniano@example.com`, `luis.rojas@example.com`, `ana.vaca@example.com` |
| Cliente | `javier.paz@example.com`, `mariela.duran@example.com`, `roberto.saucedo@example.com`, `daniela.ribera@example.com` |

---

## 2. Qué hay implementado

### Módulos de negocio

| Módulo | Responsable | Pantallas (`resources/js/pages`) | API (`routes/api`) |
|---|---|---|---|
| CU1 Usuarios, roles y matriz de permisos, unidades, clientes | A | `seguridad/`, `organizacion/` | `seguridad.php` |
| CU2 Redes sociales, plataformas, canales, seguidores | A | `redes/` | `seguridad.php` |
| CU6 Términos, páginas no autorizadas, ejecuciones | A | `monitoreo/` | `seguridad.php` |
| CU3 Empresas, visitantes, servicios, tarifas, paquetes, proformas, campañas | B | `comercial/` | `comercial.php` |
| **Portada pública** (`/` sin sesión): oferta vigente, búsqueda predictiva del catálogo en el encabezado, "Cotizar" e "Iniciar sesión". Es la primera vista del Visitante; con sesión, `/` muestra el panel de inicio del rol | A | `Portada.jsx` | `GET publico/catalogo` |
| Cotización pública sin sesión (`/cotizar`; acepta `?paquete=ID` o `?tarifa=ID` para llegar con el ítem ya en el carrito) | B | `comercial/CotizacionPublica.jsx` | `GET publico/catalogo`, `POST publico/cotizar` |
| Pagos: métodos (Luhn), pasarela simulada, pago único, planes en cuotas | B | `pagos/` | `comercial.php` |
| Búsqueda global (endpoint con permisos y "solo lo suyo") | B | — | `GET buscar?q=` |
| CU4 Material publicitario, versiones y revisiones | C | `operacion/Materiales.jsx` | `operacion.php` |
| CU5 Espacios, pautas (programar, reprogramar, emitir), notificaciones | C | `operacion/` | `operacion.php` |
| CU7 Métricas de impacto · CU8 Dashboard de negocio y accesos | C | `operacion/Metricas.jsx`, `dashboard/` | `operacion.php` |
| Barra de búsqueda predictiva y migas de pan en el encabezado | C | `components/BusquedaGlobal.jsx`, `Breadcrumbs.jsx` | — |

### Requisitos transversales

| Requisito | Dónde |
|---|---|
| Login con token (Sanctum), bloqueo tras 5 intentos | `AuthService`, `POST /auth/login` |
| Contraseñas con **bcrypt** (12 rondas); los hashes PBKDF2 que crea el sistema vía correo se aceptan y se convierten a bcrypt al iniciar sesión | `app/Support/Contrasena.php` |
| **RBAC**: privilegios por rol (`recurso.accion`) y matriz editable | tablas `privilegio`, `rol_privilegio`; pantalla *Roles y permisos* |
| **Menú dinámico desde la BD** según el rol | tabla `menu_item`, `MenuService`, `GET /auth/me` |
| **Matriz de acceso en rutas** (403 si falta el permiso) | middleware `permiso:` (`VerificarPrivilegio`) |
| **Bitácora**: logins exitosos y fallidos, IP, recursos más accedidos | tabla `bitacora` + trigger → `contador_recurso`; middleware `RegistrarBitacora`; pantalla *Bitácora* |
| **Contador de visitas** en el footer de todas las vistas | tabla `visita_pagina`, función `fn_registrar_visita`, `Footer.jsx` |
| Validación cliente + servidor con mensajes en español | `lib/validar.js` + `app/Http/Requests` + `lang/es/validation.php` |
| Temas Niños / Jóvenes / Adultos × Día/Noche (auto o manual), tamaño de letra, alto contraste | `app.css`, `PreferenciasContext`, `PanelAccesibilidad` |
| Pagos simulados: Luhn, nunca se guarda el número completo ni el CVV; tarjetas de prueba terminadas en `0002` (fondos insuficientes) y `0069` (vencida) se rechazan | `PagoService`, `MetodoPagoService` |

Roles de negocio: **Director**, **Encargado de Unidad** y **Cliente**, además del Administrador.

---

## 3. Arquitectura del backend

```
Petición HTTP ─▶ routes/api/*.php ─▶ middleware auth:sanctum ─▶ middleware permiso:x.y
              ─▶ FormRequest (valida formato, mensajes en español)
              ─▶ Controller (delgado) ─▶ Service (reglas de negocio, portadas de las clases N* del Proyecto 1)
              ─▶ Model Eloquent (tablas del esquema existente) ─▶ PostgreSQL
Respuesta JSON ◀─ RegistrarBitacora (guarda usuario, IP, recurso, código y duración)
```

**Formato de respuesta** (todos los endpoints):

```jsonc
{ "data": ..., "message": "Usuario registrado correctamente." }          // éxito
{ "data": [...], "meta": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 25 } }  // paginado
{ "message": "Revise los datos...", "errors": { "email": ["..."] } }    // 422 validación
{ "message": "No se puede dar de baja el rol..." }                       // 422 regla de negocio (NegocioException)
// 401 sin sesión · 403 sin permiso · 404 no existe · 429 demasiados intentos
```

**Reglas:**
- Ninguna regla de negocio va en un Controller ni en React: van en `app/Services`.
- Si una regla no se cumple, se lanza `throw new NegocioException('mensaje en español')`.
- Cada ruta protegida lleva `->middleware('permiso:recurso.accion')`. Los códigos están en la tabla `privilegio`.
- Filtro "solo lo suyo" (Cliente → su empresa, Encargado → su unidad): `$usuario->esCliente()`, `idEmpresa()`, `esEncargado()`, `esPersonal()`.

---

## 4. Guía rápida para Persona B y Persona C

**Backend: agregar un módulo**
1. Model en `app/Models` (`$table`, `$primaryKey`, `$timestamps = false`, `$fillable`).
2. Service en `app/Services` con la lógica de la clase `N*` equivalente del Proyecto 1.
3. FormRequest que extienda `App\Http\Requests\ApiRequest`. Si hace falta, agregar los nombres de campo en `lang/es/validation.php` → `attributes`.
4. Controller que extienda `App\Http\Controllers\Controller` y use `$this->ok()`, `$this->creado()` y `$this->paginado()`.
5. Rutas en **su** archivo: `routes/api/comercial.php` (B) o `routes/api/operacion.php` (C).
6. Para los `<select>` de sus formularios, agregar casos en `OpcionesController`.

**Frontend: agregar una pantalla**
1. Crear `resources/js/pages/<modulo>/<Pantalla>.jsx`. Para un CRUD normal, usar `<PaginaCrud .../>`
   (ver `pages/seguridad/Usuarios.jsx` como ejemplo completo).
2. En `resources/js/rutas.jsx`, reemplazar `EnConstruccion` por su componente.
3. Usar siempre `api` de `lib/api.js` (ya envía el token y maneja el 401), `useAuth().puede('x.y')` para mostrar u ocultar
   botones, y `useAvisos()` para mensajes y confirmaciones.
4. Colores solo con las clases del tema: `bg-primario`, `text-texto-suave`, `border-borde`, `btn btn-primario`, `input`, `tarjeta`…

**Estadísticas:** `GET /api/v1/bitacora/resumen?dias=14` (accesos) y `GET /api/v1/estadisticas/negocio` (negocio),
ambos con permiso `dashboard.ver`.

**Validación en el cliente:** `validar()` devuelve `{ campo: 'mensaje' }`; `validarLista()` devuelve el mismo formato que
los errores 422 del servidor (`{ campo: ['mensaje'] }`).

---

## 5. Base de datos (tablas nuevas del Proyecto 2)

Scripts idempotentes en `database/sql/`, ejecutados por las migraciones (ambas **ya aplicadas** en `db_grupo13sa`):
- `01_seguridad.sql` y `02_seguridad_datos.sql` → migración `2026_11_23_000001_seguridad_proyecto2`
- `03_pagos.sql` → migración `2026_11_24_000001_pagos_proyecto2`
- Datos de prueba de pagos: `php artisan db:seed` (`PagosSeeder`, no repite datos si ya existen)

| Tabla / objeto | Uso |
|---|---|
| `privilegio`, `rol_privilegio` | Matriz RBAC (91 privilegios) |
| `menu_item` | Menú dinámico (cada ítem exige un privilegio) |
| `bitacora` | Auditoría: login_ok, login_fallido, logout, acceso, denegado, error |
| `contador_recurso` | Accesos por recurso (lo actualiza `trg_bitacora_despues_insertar`) |
| `visita_pagina` + `fn_registrar_visita()` | Contador de visitas del footer |
| `personal_access_tokens` | Tokens de Sanctum |
| `metodo_pago`, `plan_pago`, `cuota`, `transaccion_pago` | Módulo de pagos |
| `trg_cuota_pagada` | Al pagarse todas las cuotas, el plan pasa a `completado` |

> En la BD también existen tablas de una plantilla de la facultad (`persona`, `grupo`, `modulo`, `accion`, `permiso`,
> `mod_grup`). **No se usan ni se modifican**: por eso las tablas nuevas se llaman `privilegio` y `menu_item`.

---

## 6. Ejecutar con Docker (si Windows bloquea `pdo_pgsql`)

```bash
docker run --rm -it -p 8000:8000 -v "$PWD":/app -w /app php:8.4-cli bash -c \
  "apt-get update -qq && apt-get install -y -qq libpq-dev >/dev/null && docker-php-ext-install pdo_pgsql >/dev/null \
   && php artisan serve --host=0.0.0.0 --port=8000"
```
Antes, en otra terminal: `npm run build` (o `npm run dev`).
