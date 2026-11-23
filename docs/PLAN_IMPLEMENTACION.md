# Plan de implementación — Proyecto 2 (Laravel + React, API RESTful)

**Grupo 13 SA · INF513 Tecnología Web · UAGRM**
Sistema de Gestión Publicitaria de Canal 11 / UAGRM, migrado del Proyecto 1 (vía correo) a una aplicación web.

- **Equipo:** 3 personas (A, B, C)
- **Plazo:** 3 días hábiles
- **Despliegue:** `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2`
- **Repositorio:** `https://github.com/CodeWithElias/inf513-proyecto2`

---

## 1. Idea central: qué se reutiliza y qué es nuevo

El Proyecto 1 ya resuelve el dominio completo (CU1 a CU8) en 3 capas Java. **No se rediseña el negocio: se traduce.**
La base de datos (27 tablas + datos iniciales) se reutiliza tal cual. Lo nuevo son los requisitos web: RBAC con menú
dinámico, bitácora en BD, temas, accesibilidad, contador de visitas, búsqueda global y pagos.

| Proyecto 1 (Java, correo) | Proyecto 2 (Laravel + React) |
|---|---|
| `datos/D*.java` (SQL con PreparedStatement) | `app/Models/*` (Eloquent, `$table` y `$primaryKey` del esquema actual) |
| `negocio/N*.java` (reglas y cálculos) | `app/Services/*Service.php` (misma lógica: totales de proforma, vigencias, transiciones de estado…) |
| `negocio/Validar.java` | `app/Http/Requests/*Request.php` (FormRequest, mensajes en español) + validación en React |
| `negocio/Sesion`, `Acceso` ("solo lo suyo") | `app/Policies/*` + filtros por empresa o unidad en los Services |
| `negocio/NegocioException` | `App\Exceptions\NegocioException`, que responde **422** con `{ message }` |
| `presentacion/comandos/Permisos.java` (matriz fija) | Tablas `privilegio`, `rol_privilegio` y `menu_item` + middleware `permiso:<codigo>` |
| `presentacion/comandos/Despachador` | `routes/api.php` (`/api/v1/...`) |
| `presentacion/P*.java` + `Html.java` | Páginas React (`resources/js/pages/*`) |
| `presentacion/Bitacora.java` (archivos .log) | Tabla `bitacora` + middleware que registra cada petición |
| Remitente del correo = sesión | Login con **Laravel Sanctum** (token Bearer) |
| `Notificador` (correo) | Tabla `notificacion` (ya existe) mostrada en la web; el correo es opcional |

**Roles** (ya están en `rol`): Administrador, **Director**, **Encargado de Unidad**, **Cliente**, más el Visitante sin
login (catálogo y cotización). Son 3 roles de negocio aparte del Administrador, así que se cumple el mínimo de 2.

---

## 2. Decisiones técnicas (tomar el día 1 a primera hora, no se discuten después)

1. **Un solo proyecto Laravel con React en `resources/js`** (Vite + `@vitejs/plugin-react`). React es una SPA que consume
   la API **solo con Axios**, sin Inertia. Ventajas: un único despliegue, mismo origen (sin CORS) y un solo `npm run build`.
   - Capa 1: `resources/js` (React)
   - Capa 2: `app/` + `routes/api.php` (Laravel)
   - Capa 3: PostgreSQL (`database/` con migraciones y triggers)
2. **Versión de Laravel:** primero se verifica `php -v` en el servidor de tecnoweb. Se usa la versión más nueva de Laravel
   que soporte ese PHP, y todos usan esa misma versión localmente.
3. **Autenticación:** Sanctum con tokens personales. El token se guarda en `localStorage` y Axios lo envía en
   `Authorization: Bearer`. No se usan cookies, porque bajo un subdirectorio dan problemas.
4. **Base de datos:**
   - Si el usuario `grupo13sa` puede crear esquemas: se crea el esquema `proyecto2` en `db_grupo13sa`, se cargan ahí
     `schema.sql` y `datos.sql` del Proyecto 1, y Laravel usa `search_path=proyecto2`. Así el Proyecto 1 no se toca.
   - Si no puede: se usa `public` y solo se hacen **cambios aditivos** (tablas nuevas, nunca `ALTER` sobre las 27 existentes).
   - Las tablas nuevas se crean con **migraciones de Laravel**.
5. **Contraseñas:** `usuario.password_hash` está en PBKDF2 (`pbkdf2$iter$sal$hash`). Hay dos opciones:
   - Un guard o provider propio que verifique con `hash_pbkdf2('sha256', ...)`, para que los usuarios existentes sigan funcionando.
   - Un seeder que reescriba las contraseñas de prueba con `Hash::make`.

   **Recomendado:** la verificación PBKDF2. Al iniciar sesión se re-hashea a bcrypt solo si se usa un esquema propio.
6. **Estilos:** Tailwind CSS con **variables CSS** por tema (`--color-primario`, `--fondo`, `--radio`, `--fuente`…). Los
   temas cambian `data-tema` y `data-modo` en `<html>`. Gráficos con **Recharts**. Rutas con **React Router**
   (`basename="/inf513/grupo13sa/proyecto2"`). En `vite.config`, `base` apunta al mismo subdirectorio.
7. **Formato de la API:**
   - Prefijo `/api/v1`.
   - Éxito: `{ data, meta? }`.
   - Error de validación (422): `{ message, errors: { campo: ["mensaje en español"] } }`.
   - Otros errores: 401 sin sesión, 403 sin permiso, 404, 422 de negocio.
   - Paginación con `?page=&per_page=&q=`.
8. **Idioma:** `APP_LOCALE=es` y `lang/es/validation.php` completo, con `attributes` legibles ("razón social", no "razon_social").

---

## 3. Modelo de datos nuevo (migraciones)

| Tabla | Campos principales | Requisito |
|---|---|---|
| `privilegio` | `id_privilegio`, `codigo` (`proformas.ver`, `proformas.crear`…), `recurso`, `accion`, `descripcion` | 2, 4 |
| `rol_privilegio` | `id_rol`, `id_privilegio` (PK compuesta) | 2, 4 |
| `menu_item` | `id_menu`, `titulo`, `ruta`, `icono`, `orden`, `id_padre`, `codigo_privilegio` | 2 (menú desde la BD) |
| `bitacora` | `id`, `id_usuario` null, `email_intento`, `evento` (`login_ok`, `login_fallido`, `logout`, `acceso`, `denegado`), `metodo`, `recurso`, `ip`, `user_agent`, `codigo_http`, `fecha` | 4 |
| `contador_recurso` | `recurso` PK, `total`, `ultimo_acceso` (se actualiza con un **trigger** al insertar en `bitacora`) | 4 (recursos más accedidos) |
| `visita_pagina` | `ruta` PK, `total`, `ultima_visita` (UPSERT atómico o función SQL `fn_registrar_visita`) | 7 |
| `metodo_pago` | `id_metodo`, `id_usuario`, `tipo` (tarjeta, QR, billetera), `marca`, `ultimos4`, `titular`, `mes_exp`, `anio_exp`, `token_simulado`, `predeterminado`, `activo` | 10 |
| `plan_pago` | `id_plan`, `id_usuario`, `id_proforma`, `monto_total`, `nro_cuotas`, `frecuencia`, `fecha_inicio`, `estado` | 10 |
| `cuota` | `id_cuota`, `id_plan`, `nro`, `monto`, `fecha_vencimiento`, `estado` (pendiente, pagada, vencida) | 10 |
| `transaccion_pago` | `id_transaccion`, `id_usuario`, `id_metodo`, `id_proforma` null, `id_cuota` null, `monto`, `estado` (aprobada, rechazada), `codigo_autorizacion`, `mensaje`, `fecha` | 10 |
| `personal_access_tokens` | la crea Sanctum | 3 |

**Triggers y funciones (Capa 3):**
- `trg_bitacora_contador`: incrementa `contador_recurso`.
- `fn_registrar_visita(ruta)`: incrementa y devuelve el total.
- `trg_cuota_pagada`: cuando se pagan todas las cuotas, el plan pasa a `completado`.

**Seeders:**
- Permisos derivados de la matriz actual de `Permisos.java` (ver la sección *Seguridad* del README del Proyecto 1).
- `menu_item` por módulo.
- Métodos de pago y transacciones de ejemplo, para que el dashboard tenga datos.

**Pasarela simulada (sin cobros reales):**
- Se valida el número con el algoritmo de **Luhn**, la fecha de expiración y el CVV de 3 o 4 dígitos.
- **Nunca se guardan el número completo ni el CVV**: solo la marca, los últimos 4 dígitos y un token.
- Tarjetas de prueba: terminada en `0002` → rechazada ("Fondos insuficientes"); terminada en `0069` → rechazada
  ("Tarjeta vencida"); cualquier otra válida → aprobada con código `AUT-XXXXXX`.

---

## 4. Endpoints principales (`/api/v1`)

| Grupo | Endpoints |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` (usuario, rol, permisos y **menú**) |
| Públicos | `GET /catalogo/servicios`, `/catalogo/tarifas`, `/catalogo/paquetes`, `POST /cotizaciones` (visitante), `POST /visitas` (`{ruta}` → `{total}`), `GET /buscar?q=` |
| CU1 | `apiResource` de `usuarios`, `roles`, `unidades`, `representantes`; `PATCH /usuarios/{id}/estado`; `PUT /roles/{id}/permisos` |
| CU2 | `plataformas`, `canales`, `redes-sociales`, `redes-sociales/{id}/seguidores` |
| CU3 | `empresas`, `visitantes`, `servicios`, `tarifas`, `paquetes` (+ ítems), `proformas` (+ `detalles`, `PATCH aprobar/rechazar`), `campanias` |
| CU4 | `materiales` (+ `versiones`), `materiales/{id}/revisiones` (aprobar, rechazar, corrección) |
| CU5 | `espacios` (+ `disponibilidad`), `pautas` (+ reprogramar, cancelar, publicar), `notificaciones` (+ marcar leída) |
| CU6 | `terminos`, `paginas-no-autorizadas` (+ estado), `ejecuciones` |
| CU7 | `metricas`, `analisis/pauta/{id}`, `analisis/campania/{id}`, `analisis/red/{id}` |
| CU8 / Dashboard | `GET /estadisticas/negocio`, `GET /estadisticas/accesos`, `GET /reportes/{tipo}?desde=&hasta=` |
| Bitácora | `GET /bitacora` (filtros por evento, usuario, IP y fechas), `GET /bitacora/recursos-top` |
| Pagos | `metodos-pago`, `POST /pagos` (pago único de proforma o cuota), `GET /pagos` (historial), `planes-pago` (crear plan → genera cuotas), `POST /planes-pago/{id}/cuotas/{nro}/pagar` |

**Middlewares:**
- `auth:sanctum`.
- `permiso:<codigo>`: consulta `rol_privilegio` (cacheado por rol) y responde 403 si falta el permiso.
- `bitacora`: registra método, ruta, IP, usuario y código de respuesta de cada petición `/api`.

El login registra `login_ok` o `login_fallido` con el email intentado y la IP. Además, `throttle` limita a 5 intentos por minuto.

---

## 5. Reparto de trabajo

Cada persona se encarga de **módulos verticales completos** (API + pantallas React), para no depender todo el tiempo de los
demás. El día 1 se construye la base común; desde el día 2 cada uno avanza en paralelo.

### Persona A — Plataforma, seguridad y despliegue
- Repositorio, proyecto Laravel + React, `.env.example`, conexión a PostgreSQL y prueba de despliegue en tecnoweb.
- Login con Sanctum + verificación PBKDF2, `GET /auth/me` con permisos y menú.
- Tablas `privilegio`, `rol_privilegio`, `menu_item` y `bitacora`, más triggers; middlewares `permiso` y `bitacora`.
- Contador de visitas (API + trigger o función).
- Pantallas: Login, administración de **Usuarios, Roles y Permisos** (matriz con casillas por rol), Unidades, Representantes (CU1), Bitácora.
- CU2 (redes sociales) y CU6 (monitoreo: términos y páginas detectadas con su estado).
- Responsable del **despliegue final** y del README.

### Persona B — Negocio comercial y pagos
- CU3 completo: empresas, visitantes, servicios, tarifas (vigencia), paquetes (descuento y precio total), **proformas**
  (portar exactamente los cálculos de `NProforma`: subtotal, descuento, total, número `PRO-AAAA-NNNNN`, vencimiento) y campañas.
- Cotización pública del visitante.
- **Módulo de pagos completo**: métodos de pago, pasarela simulada, pago único, planes de pago con cuotas e historial.
- Endpoint de **búsqueda global** `GET /buscar?q=` (empresas, servicios, paquetes, campañas y proformas, filtrado por permisos
  del usuario; máximo 5 resultados por tipo).
- Seeders de datos de pagos para el dashboard.

### Persona C — Experiencia de usuario, operación y estadísticas
- Estructura base de React: layout (Header, Sidebar con menú dinámico, Footer), router, cliente Axios con interceptores
  (token; 401 → login; 422 → errores en el formulario), contexto de sesión.
- Componentes comunes que usan todos: `Tabla` (paginación y búsqueda), `Formulario` / `Campo` (muestra errores del cliente y del
  servidor), `Modal`, `Confirmar`, `Alerta`, `Insignia de estado`.
- **Temas**: Niños, Jóvenes y Adultos × Día/Noche (automático según la hora: día de 06:00 a 18:59, o manual).
  **Accesibilidad**: tamaño de fuente (A−, A, A+ o deslizador de 87,5 % a 150 %) y alto contraste. Todo se guarda en `localStorage`.
- **Barra de búsqueda predictiva en el Header** (espera 300 ms al escribir, se maneja con teclado y agrupa por tipo).
- **Footer con contador** (llama a `POST /visitas` en cada cambio de ruta).
- CU4 (material y revisiones), CU5 (espacios, pautas y notificaciones), CU7 (métricas y análisis).
- **Dashboard** con Recharts y endpoints `/estadisticas/*`: ingresos por mes, proformas por estado, ocupación de espacios,
  top servicios, accesos por día, logins exitosos frente a fallidos, top recursos y top IP.

---

## 6. Cronograma

> Rutina diaria: **reunión de 15 min a las 09:00** (qué hice, qué haré, qué me bloquea) y **merge a `develop` a las 18:00**
> con la app funcionando. Nadie hace push directo a `main`.

### Día 1 — Base común y contratos
| Hora | A | B | C |
|---|---|---|---|
| Mañana | Crea el repo y el proyecto Laravel + React + Tailwind; conexión a la BD; decide esquema y contraseñas (sección 2) | Lee `NProforma`, `NTarifa`, `NPaquete` y `NCampania`; crea Models de CU3 con las relaciones | Router, layout, cliente Axios y contexto de sesión; con el repo listo, empieza los componentes comunes |
| Tarde | Sanctum + login PBKDF2, `/auth/me`; migraciones RBAC, menú y bitácora + seeders de permisos | `lang/es/validation.php` + `NegocioException` + formato de respuesta; CRUD de empresas y servicios como **ejemplo de referencia** para el equipo | Pantalla de login conectada, Sidebar con el menú de `/auth/me`, sistema de temas y accesibilidad |
| Cierre | **Despliegue de prueba en tecnoweb** (aunque sea el login): es el mayor riesgo del proyecto | Migraciones de pagos | `Tabla` y `Formulario` reutilizables con un CRUD de ejemplo |

**Entregable del día 1:** login funcionando localmente y en el servidor, menú dinámico según el rol, un CRUD de referencia de
punta a punta (FormRequest en español → Service → Model, y en React Tabla + Formulario con errores) y temas visibles.

### Día 2 — Módulos de negocio en paralelo
| A | B | C |
|---|---|---|
| Middlewares `permiso` y `bitacora` aplicados a todas las rutas; contador de visitas (API + trigger); Usuarios, Roles y matriz de permisos, Unidades, Representantes | Tarifas, paquetes, **proformas con detalle y cálculo**, aprobar o rechazar, campañas; cotización del visitante | CU4 material y revisiones; CU5 espacios (sin superposición) y pautas; notificaciones |
| Pantalla de bitácora con filtros; CU2 redes sociales y seguidores | Métodos de pago + pasarela simulada + pago único | Footer con contador, Header con búsqueda conectada a `/buscar` (cuando B lo publique) |

**Entregable del día 2:** todos los CRUD principales funcionando con permisos por rol y "solo lo suyo" para Cliente y Encargado.

### Día 3 — Cierre, integración y despliegue
| Hora | A | B | C |
|---|---|---|---|
| Mañana | CU6 monitoreo; revisa que **cada ruta** tenga su permiso y que el menú coincida | Planes de pago + cuotas + historial; búsqueda global; endpoints de estadísticas de negocio | Dashboard con gráficos; CU7 métricas y análisis; pulido visual de los 3 temas en día y noche |
| 14:00 | **Congelamiento de funciones.** Solo se corrigen errores. | | |
| Tarde | Despliegue final (`composer install --no-dev`, `npm run build`, `migrate --seed`, caché de config y rutas) | Prueba cruzada: recorre el sistema como **Cliente** | Prueba cruzada: recorre el sistema como **Encargado** y **Visitante**; revisa accesibilidad (contraste y zoom) |
| Cierre | README (instalación, despliegue, usuarios de prueba) | Guion de demostración | Capturas para el informe |

---

## 7. Lista de verificación final (contra los 10 requisitos del enunciado)

| # | Requisito | Cómo se demuestra |
|---|---|---|
| 1 | Diseño y navegación | Layout coherente, Sidebar según permisos, migas de pan y diseño adaptable a celular |
| 2 | RBAC y menú desde la BD | Se entra como Director, Encargado y Cliente → menús distintos; se quita un permiso en la matriz → la opción desaparece |
| 3 | Laravel API + React | Pestaña Red del navegador: todo es JSON por `/api/v1` con Axios |
| 4 | Matriz de acceso y bitácora | Una ruta sin permiso responde 403 (y queda registrada como `denegado`); bitácora con logins exitosos y fallidos, IP y top recursos |
| 5 | Temas y accesibilidad | Selector Niños / Jóvenes / Adultos, Día/Noche automático y manual, tamaño de fuente y alto contraste en todas las vistas |
| 6 | Validación cliente y servidor | Formulario vacío → errores en el cliente; desactivando la validación del cliente (o con Postman) → 422 con mensajes en español |
| 7 | Contador de visitas | Footer de cada vista con "Esta página ha sido visitada N veces", que sigue contando al recargar |
| 8 | Dashboard | Gráficos de negocio y de accesos |
| 9 | Búsqueda global | Barra predictiva en el Header |
| 10 | Pagos | Registrar una tarjeta (Luhn), pagar una proforma, una tarjeta rechazada, crear un plan de 3 cuotas y pagar una |
| — | Despliegue | Funciona en `tecnoweb.org.bo/inf513/grupo13sa/proyecto2` |
| — | GitHub | `inf513-proyecto2` con commits de **los 3 integrantes** |

---

## 8. Alcance: qué se recorta si falta tiempo

**No se negocia** (son los requisitos del enunciado): los puntos 1 a 10 de la tabla anterior, más CU1 y CU3 completos.

**Se simplifica en este orden:**
1. **CU6 scraping real de YouTube:** solo se registran términos y páginas detectadas a mano, con su estado. El scraping
   automático queda como mejora.
2. **Correos de notificación (RF11):** solo se guardan en `notificacion` y se muestran en la web.
3. **CU4 subida de archivos:** se mantiene el registro por URL, como en el Proyecto 1.
4. **CU8 reportes por periodo:** se reemplazan por los gráficos del dashboard con filtro de fechas.
5. **CU7 captura automática:** solo se cargan métricas manualmente.

---

## 9. Riesgos y cómo se reducen

| Riesgo | Mitigación |
|---|---|
| Versión de PHP o extensiones del servidor (`pdo_pgsql`) incompatibles; sin `composer` ni SSH | Se verifica el **día 1 por la mañana**. Si no hay composer en el servidor, se sube `vendor/` y `public/build` ya construidos |
| App bajo subdirectorio (`/proyecto2`): rutas, assets y `.htaccess` | `APP_URL` con el subdirectorio, `base` de Vite, `basename` del router, `.htaccess` con `RewriteBase` y ruta comodín de Laravel que devuelve la SPA |
| BD compartida con el Proyecto 1 | Esquema separado o solo cambios aditivos; nunca `migrate:fresh` contra el servidor |
| Contraseña del grupo en el historial del repo del Proyecto 1 | El repo nuevo empieza limpio; `.env` en `.gitignore` desde el primer commit |
| Integración tardía | Merge diario a `develop` y CRUD de referencia del día 1 como plantilla obligatoria |
| Conflictos en archivos compartidos (`routes/api.php`, seeders, router de React) | Cada persona tiene su archivo de rutas (`routes/api/comercial.php`, etc.) incluido desde `api.php`, y su archivo de rutas React por módulo |

---

## 10. Convenciones del equipo

- **Ramas:** `main` (entrega), `develop` (integración) y `feature/<persona>-<modulo>` (ej. `feature/b-proformas`). PR a
  `develop` revisado por otra persona.
- **Commits:** en español, en presente: `agrega cálculo de total en proformas`.
- **Backend:** Controller fino (recibe un FormRequest y llama al Service) → Service con las reglas (portadas de `N*`) →
  Model. **Ninguna regla de negocio en los Controllers ni en React.**
- **Frontend:** `pages/<modulo>/` y `services/<modulo>.js` (llamadas Axios). Componentes comunes en `components/`. Colores
  siempre con variables del tema, nunca hexadecimales sueltos.
- **Usuarios de prueba** (uno por rol), documentados en el README.
