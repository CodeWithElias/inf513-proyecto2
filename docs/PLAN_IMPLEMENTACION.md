# Plan de implementación — Proyecto 2 (Laravel + React, API RESTful)

**Grupo 13 SA · INF513 Tecnología Web · UAGRM**
Sistema de Gestión Publicitaria de TVU / Canal 11 UAGRM, migrado del Proyecto 1 (vía correo) a una aplicación web.

- **Equipo:** 3 personas (A, B, C) · **Plazo:** 3 días hábiles (lun 23/11 al mié 25/11/2026)
- **Despliegue:** `https://www.tecnoweb.org.bo/inf513/grupo13sa/proyecto2`
- **Repositorio:** `https://github.com/CodeWithElias/inf513-proyecto2`
- **Última actualización:** 23/11/2026 (fin del día 1)

---

## 0. Estado general

| Bloque | Responsable | Estado |
|---|---|---|
| Proyecto base (Laravel 12 + React 19 + Tailwind 4), conexión a la BD en línea | A | ✅ Hecho |
| Seguridad: login (Sanctum), RBAC, menú dinámico, bitácora, contador de visitas | A | ✅ Hecho |
| Estructura base del frontend (layout, menú, Axios, sesión, componentes comunes, `PaginaCrud`) | A (era de C) | ✅ Hecho |
| Temas Niños/Jóvenes/Adultos × Día/Noche, accesibilidad, logo y colores TVU | A (base) / C (pulido) | 🟡 Funciona; falta pulido visual |
| CU1 Usuarios, roles y matriz de permisos, unidades, clientes | A | ✅ Hecho |
| CU2 Redes sociales, plataformas, canales, seguidores | A | ✅ Hecho |
| CU6 Términos, páginas no autorizadas, ejecuciones | A | ✅ Hecho |
| CU3 Comercial (empresas, servicios, tarifas, paquetes, proformas, campañas, cotización del visitante) | B | ⬜ Pendiente |
| Pagos (métodos, pasarela simulada, pago único, planes y cuotas) | B | ⬜ Pendiente |
| Búsqueda global: endpoint `/buscar` (B) + barra predictiva en el encabezado (C) | B + C | ⬜ Pendiente |
| CU4 Material, CU5 Espacios/Pautas/Notificaciones, CU7 Métricas | C | ⬜ Pendiente |
| Dashboard de estadísticas (negocio + accesos) | C | ⬜ Pendiente (el endpoint de accesos ya existe) |
| Repositorio en GitHub (primer push) | A | ⬜ Pendiente (repo local listo, sin commit) |
| Despliegue en tecnoweb | A | ⬜ Pendiente (falta acceso al servidor) |

---

## 1. Lo que ya está hecho (Persona A, día 1)

### 1.1 Decisiones tomadas (ya no se discuten)

| Tema | Decisión final |
|---|---|
| Estructura | **Un solo proyecto Laravel**; React vive en `resources/js` y consume la API **solo por Axios** (sin Inertia). Cumple las 3 capas: React → API Laravel → PostgreSQL |
| Base de datos | Se usa **directamente `db_grupo13sa` en línea, esquema `public`** (la del Proyecto 1). Solo cambios **aditivos**: nunca `ALTER` ni `DROP` sobre las 27 tablas del Proyecto 1 |
| Tablas de la facultad | En la BD hay tablas de una plantilla (`persona`, `grupo`, `modulo`, `accion`, `permiso`, `mod_grup`). **No se usan ni se tocan.** Por eso nuestras tablas se llaman `privilegio` y `menu_item` |
| Autenticación | **Laravel Sanctum** (token Bearer guardado en `localStorage`) |
| Contraseñas | **bcrypt** (`Hash::make`, que usa `password_hash()` de PHP). Los hashes PBKDF2 que crea el sistema vía correo se aceptan y se convierten a bcrypt al iniciar sesión |
| Contraseña de todos los usuarios | **`clave123!`** |
| Idioma | `APP_LOCALE=es`, mensajes de validación en `lang/es/validation.php` |
| Identidad visual | Logo TVU (`public/img/logo-tvu.png`), azul `#0A35A8` y rojo `#D01010` en todos los temas |

### 1.2 Base de datos (ya aplicado en la BD en línea)

Scripts en `database/sql/` (idempotentes), ejecutados por la migración `2026_11_23_000001_seguridad_proyecto2`:

| Objeto | Para qué |
|---|---|
| `privilegio` (91 códigos `recurso.accion`) y `rol_privilegio` | Matriz RBAC. Ya existen los códigos de **todos** los módulos, incluidos los de B y C |
| `menu_item` (37 ítems) | Menú dinámico; cada ítem exige un privilegio |
| `bitacora` + trigger `trg_bitacora_despues_insertar` → `contador_recurso` | Auditoría: login_ok, login_fallido, logout, acceso, denegado, error; recursos más accedidos |
| `visita_pagina` + función `fn_registrar_visita()` | Contador de visitas del pie de página |
| `personal_access_tokens` | Tokens de Sanctum |

Asignación inicial: Administrador 91 privilegios, Director 79, Encargado de Unidad 10, Cliente 28.

### 1.3 Endpoints disponibles (`/api/v1`)

| Grupo | Endpoints |
|---|---|
| Auth | `POST auth/login`, `POST auth/logout`, `GET auth/me` (usuario, privilegios y menú) |
| Públicos | `POST visitas` `{ruta}` → `{total}` |
| Apoyo | `GET opciones/{tipo}` → `[{id, nombre}]` para los `<select>` (roles, unidades, empresas, plataformas, usuarios, usuarios-sin-cliente) |
| Bitácora | `GET bitacora` (filtros), `GET bitacora/resumen?dias=14` (totales, serie por día, top recursos, top IP; permiso `bitacora.ver` o `dashboard.ver`) |
| CU1 | `usuarios`, `roles`, `roles/{id}/privilegios` (GET/PUT), `unidades`, `representantes` + `PATCH .../estado` |
| CU2 | `plataformas`, `canales`, `redes`, `redes/{id}/seguidores` |
| CU6 | `terminos`, `paginas` (+ `PATCH paginas/{id}/estado`), `ejecuciones` |

### 1.4 Frontend disponible para todos

| Pieza | Archivo | Uso |
|---|---|---|
| Cliente HTTP | `lib/api.js` | `api.get/post/...`: envía el token, maneja el 401 y da `mensajeError()` |
| Sesión y permisos | `context/AuthContext.jsx` | `const { usuario, puede } = useAuth(); puede('pagos.crear')` |
| Avisos y confirmaciones | `context/AvisosContext.jsx` | `exito()`, `error()`, `await confirmar('¿Seguro?')` |
| CRUD configurable | `components/PaginaCrud.jsx` | Tabla + búsqueda + filtros + modal con validación + baja/reactivación. **Ejemplo completo: `pages/seguridad/Usuarios.jsx`** |
| Validación en el cliente | `lib/validar.js` | Reglas: requerido, min, max, email, url, entero, minValor, maxValor, patron, fecha, hastaHoy |
| Componentes sueltos | `components/` | `Tabla`, `Modal`, `Campo`, `Insignia`, `EncabezadoPagina`, `Logo` |
| Rutas de pantallas | `rutas.jsx` | Cada uno reemplaza `EnConstruccion` por su componente |
| Formatos | `lib/formato.js` | `fecha()`, `fechaHora()`, `numero()`, `moneda()` (Bs) |

### 1.5 Probado contra la BD en línea

Login correcto e incorrecto, validación 422 en español, menú distinto por rol (Director, Encargado, Cliente), 403 sin permiso
(registrado como `denegado`), filtro "solo lo suyo" del Encargado en redes, reglas de negocio (ciclo de unidades, flujo de
estados de páginas, tipo de canal), contador de visitas, resumen de bitácora y logout que invalida el token.

---

## 2. Lo que falta y quién lo hace

### Persona B — Comercial, pagos y búsqueda (backend + pantallas)

| # | Tarea | Detalle | Prioridad |
|---|---|---|---|
| B1 | **CU3 Empresas, servicios, tarifas, paquetes** | Portar `NEmpresa`, `NServicio`, `NTarifa` (vigencia), `NPaquete` (descuento y `precio_total`). El modelo `EmpresaAnunciante` ya existe: ampliarlo | Alta |
| B2 | **CU3 Proformas y campañas** | Portar **exactamente** `NProforma`: subtotal, descuento, total, número `PRO-AAAA-NNNNN`, vencimiento, aprobar o rechazar; `NCampania` | Alta |
| B3 | Cotización pública del visitante | Catálogo y proforma sin login (registra `visitante`) | Media |
| B4 | **Pagos: tablas** | Migración con `metodo_pago`, `plan_pago`, `cuota`, `transaccion_pago` (ver sección 3) y trigger `trg_cuota_pagada` | Alta |
| B5 | **Pagos: lógica y pantallas** | Métodos de pago (Luhn, solo últimos 4 dígitos), pasarela simulada, pago único de proforma, plan de cuotas, pagar cuota, historial | Alta |
| B6 | **Búsqueda global (backend)** | `GET /buscar?q=` en empresas, servicios, paquetes, campañas y proformas; respeta permisos y "solo lo suyo"; máx. 5 por tipo | Alta |
| B7 | Datos de prueba de pagos | Seeder para que el dashboard tenga datos | Media |
| B8 | Opciones para selects | Agregar sus listas en `OpcionesController` (servicios, tarifas, paquetes, proformas…) | Según necesite |

Archivos de B: `routes/api/comercial.php`, `app/Services/*` y `app/Http/Controllers/Api/V1/*` de su módulo, y `pages/comercial/*`, `pages/pagos/*`.
Privilegios ya creados: `empresas.*`, `visitantes.ver`, `servicios.*`, `tarifas.*`, `paquetes.*`, `proformas.*` (+ `aprobar`,
`descuento`), `campanias.*`, `metodos_pago.*`, `pagos.*`, `planes_pago.*`.

### Persona C — Operación, estadísticas e interfaz

| # | Tarea | Detalle | Prioridad |
|---|---|---|---|
| C1 | **CU4 Material y revisiones** | Portar `NMaterial` (validación de formato por tipo de canal y duración), revisión: aprobar, rechazar o pedir corrección, versiones | Alta |
| C2 | **CU5 Espacios, pautas, notificaciones** | Portar `NEspacio` (sin superposición), `NPauta` (programar, reprogramar, cancelar, publicar), `NNotificacion` (marcar leída) | Alta |
| C3 | CU7 Métricas y análisis | Portar `NMetrica` y `NAnalisis` (por pauta, campaña y red) | Media |
| C4 | **Dashboard** (`/dashboard`) | Gráficos de negocio (ingresos por mes, proformas por estado, ocupación, top servicios) con endpoint `/estadisticas/negocio`, y de accesos reutilizando `GET /bitacora/resumen` | Alta |
| C5 | **Barra de búsqueda predictiva** | En el encabezado (`components/Layout.jsx`, marcado `slot-busqueda`): espera 300 ms, se maneja con teclado, agrupa por tipo. Usa `/buscar` de B | Alta |
| C6 | Pulido de temas | Revisar los 3 temas en día y noche, y alto contraste, en todas las pantallas; ajustar colores en `resources/css/app.css` | Media |
| C7 | Migas de pan (breadcrumbs) | Componente bajo el encabezado según la ruta y el menú | Baja |
| C8 | CU8 Reportes | Reemplazados por el dashboard con filtro de fechas (ver sección 5) | Baja |

Archivos de C: `routes/api/operacion.php`, sus Services y Controllers, y `pages/operacion/*`, `pages/dashboard/*`.
Privilegios ya creados: `materiales.*`, `revisiones.*`, `espacios.*`, `pautas.*`, `notificaciones.*`, `metricas.*`,
`analisis.ver`, `reportes.ver`, `dashboard.ver`.

### Persona A — Integración, despliegue y soporte

| # | Tarea | Detalle | Prioridad |
|---|---|---|---|
| A1 | **Subir a GitHub** | Primer commit y push de `main`, crear `develop`, invitar a B y C | **Inmediata** (bloquea a B y C) |
| A2 | **Verificar el servidor de tecnoweb** | `php -v` (Laravel 12 necesita 8.2+), `pdo_pgsql`, composer, SSH o FTP | **Inmediata** (mayor riesgo) |
| A3 | Despliegue de prueba y final | Seguir `docs/DESPLIEGUE.md` | Alta |
| A4 | Revisar PRs de B y C | Que cada ruta tenga `permiso:` y que no haya reglas de negocio en Controllers ni en React | Alta |
| A5 | Ítems de menú nuevos | Si B o C necesitan pantallas que no están en `menu_item`, A los agrega en `02_seguridad_datos.sql` | Según pidan |
| A6 | README final y usuarios de prueba | Instalación, despliegue, capturas | Media |

---

## 3. Tablas de pagos (las crea B)

| Tabla | Campos principales |
|---|---|
| `metodo_pago` | `id_metodo`, `id_usuario`, `tipo` (tarjeta, QR, billetera), `marca`, `ultimos4`, `titular`, `mes_exp`, `anio_exp`, `token_simulado`, `predeterminado`, `activo` |
| `plan_pago` | `id_plan`, `id_usuario`, `id_proforma`, `monto_total`, `nro_cuotas`, `frecuencia`, `fecha_inicio`, `estado` |
| `cuota` | `id_cuota`, `id_plan`, `nro`, `monto`, `fecha_vencimiento`, `estado` (pendiente, pagada, vencida) |
| `transaccion_pago` | `id_transaccion`, `id_usuario`, `id_metodo`, `id_proforma` null, `id_cuota` null, `monto`, `estado` (aprobada, rechazada), `codigo_autorizacion`, `mensaje`, `fecha` |

**Pasarela simulada:** validar el número con **Luhn**, la fecha de expiración y el CVV de 3 o 4 dígitos. **Nunca guardar el
número completo ni el CVV.** Tarjetas de prueba: terminada en `0002` → rechazada ("Fondos insuficientes"); en `0069` →
rechazada ("Tarjeta vencida"); cualquier otra válida → aprobada con código `AUT-XXXXXX`.

Crear las tablas con un script en `database/sql/03_pagos.sql` (idempotente: `CREATE TABLE IF NOT EXISTS`) y una migración
que lo ejecute, igual que `2026_11_23_000001_seguridad_proyecto2`.

---

## 4. Cronograma restante

> Reunión de 15 min a las 09:00 y merge a `develop` a las 18:00 con la app funcionando. Nadie hace push directo a `main`.

### Día 2 — martes 24/11
| | A | B | C |
|---|---|---|---|
| Mañana | A1 GitHub (a primera hora) · A2 verificar servidor | B1 empresas, servicios, tarifas, paquetes | C1 material y revisiones |
| Tarde | A3 despliegue de prueba · A4 revisar PRs | B2 proformas y campañas · B4 tablas de pagos | C2 espacios, pautas, notificaciones |

**Entregable:** CU3, CU4 y CU5 funcionando con permisos; la app de prueba visible en tecnoweb.

### Día 3 — miércoles 25/11
| Hora | A | B | C |
|---|---|---|---|
| Mañana | A4 revisión · A5 ítems de menú | B5 pagos completos · B6 búsqueda · B7 datos de prueba | C4 dashboard · C5 barra de búsqueda · C3 métricas |
| 14:00 | **Congelamiento de funciones.** Solo se corrigen errores. | | |
| Tarde | A3 despliegue final | Prueba cruzada como **Cliente** y **Visitante** | Prueba cruzada como **Encargado**; C6 temas y contraste |
| Cierre | A6 README | Guion de demostración | Capturas para el informe |

---

## 5. Alcance: qué se recorta si falta tiempo

**No se negocia** (requisitos del enunciado): los puntos 1 a 10 de la sección 6, más CU1 y CU3 completos.

**Se simplifica en este orden:**
1. **CU6 scraping automático:** ya resuelto así: la web registra páginas a mano y muestra las ejecuciones del sistema vía correo.
2. **Correos de notificación (RF11):** solo se guardan en `notificacion` y se muestran en la web.
3. **CU4 subida de archivos:** registro por URL, como en el Proyecto 1.
4. **CU8 reportes por periodo:** se reemplazan por el dashboard con filtro de fechas.
5. **CU7 captura automática:** solo métricas cargadas a mano.
6. **Migas de pan (C7):** opcional.

---

## 6. Lista de verificación final (contra los 10 requisitos del enunciado)

| # | Requisito | Estado | Cómo se demuestra |
|---|---|---|---|
| 1 | Diseño y navegación | ✅ (migas de pan pendientes) | Layout coherente, menú según permisos, adaptable a celular |
| 2 | RBAC y menú desde la BD | ✅ | Entrar como Director, Encargado y Cliente → menús distintos; quitar un permiso en *Roles y permisos* → la opción desaparece |
| 3 | Laravel API + React | ✅ | Pestaña Red del navegador: todo es JSON por `/api/v1` con Axios |
| 4 | Matriz de acceso y bitácora | ✅ | Ruta sin permiso → 403, registrada como `denegado`; pantalla *Bitácora* con logins, fallos, IP y top recursos |
| 5 | Temas y accesibilidad | ✅ (falta pulido, C6) | Botón de accesibilidad del encabezado: Niños / Jóvenes / Adultos, Día/Noche auto o manual, tamaño de letra, alto contraste |
| 6 | Validación cliente y servidor | ✅ en módulos de A · ⬜ B y C | Formulario vacío → errores en el cliente; con Postman → 422 en español |
| 7 | Contador de visitas | ✅ | Pie de página: "Esta página ha sido visitada N veces" |
| 8 | Dashboard | ⬜ C4 | Gráficos de negocio y de accesos |
| 9 | Búsqueda global | ⬜ B6 + C5 | Barra predictiva en el encabezado |
| 10 | Pagos | ⬜ B4 + B5 | Registrar tarjeta, pagar proforma, tarjeta rechazada, plan de 3 cuotas y pagar una |
| — | Despliegue | ⬜ A3 | Funciona en `tecnoweb.org.bo/inf513/grupo13sa/proyecto2` |
| — | GitHub | ⬜ A1 | `inf513-proyecto2` con commits de **los 3 integrantes** |

---

## 7. Riesgos y avisos

| Riesgo / aviso | Qué hacer |
|---|---|
| **Lentitud en local** (2 a 6 s por petición): la BD en línea está a unos 400 ms de distancia y `php artisan serve` atiende una petición a la vez | Es normal en desarrollo; en el servidor de tecnoweb será rápido. Ya se optimizó la conexión (`DB_PERSISTENT=true` en `.env` local). Si molesta mucho, usar una copia local de PostgreSQL |
| PHP del servidor desconocido | A2 a primera hora del día 2. Si es menor a 8.2, hay que bajar a Laravel 11 |
| Windows bloquea `pdo_pgsql` ("Application Control policy") | Desactivar Smart App Control (Seguridad de Windows → Control de aplicaciones y navegador) y activar `extension=pdo_pgsql` en `php.ini`. Reiniciar `php artisan serve` después |
| BD compartida con el Proyecto 1 | Nunca `migrate:fresh` ni `migrate:reset`. Las tablas nuevas, con `CREATE TABLE IF NOT EXISTS` |
| Conflictos en archivos compartidos | Cada uno tiene su archivo de rutas (`routes/api/*.php`) y sus carpetas de páginas. En `rutas.jsx` y `OpcionesController` solo se agregan líneas |
| Mensajes de validación | `lang/es/validation.php` tiene las reglas comunes. Si usan una regla nueva (ej. `decimal`, `mimes`), agregar su mensaje y los nombres de campo en `attributes` |

---

## 8. Convenciones del equipo

- **Ramas:** `main` (entrega), `develop` (integración) y `feature/<persona>-<modulo>` (ej. `feature/b-proformas`). PR a `develop` revisado por otra persona.
- **Commits:** en español, en presente: `agrega cálculo de total en proformas`.
- **Backend:** Controller fino (FormRequest → Service → Model). Reglas de negocio **solo** en `app/Services`, portadas de las
  clases `N*` del Proyecto 1. Regla no cumplida → `throw new NegocioException('mensaje en español')`. Cada ruta con
  `->middleware('permiso:recurso.accion')`. "Solo lo suyo": `$usuario->esCliente()`, `idEmpresa()`, `esEncargado()`, `esPersonal()`.
- **Frontend:** usar `api` (nunca axios directo), `useAuth().puede()` para mostrar u ocultar botones y `PaginaCrud` para los
  CRUD. Colores solo con las clases del tema (`bg-primario`, `text-texto-suave`, `btn btn-primario`, `input`, `tarjeta`…).
- **Usuarios de prueba:** contraseña `clave123!` para todos (ver README).
