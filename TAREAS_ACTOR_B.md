# Tareas implementadas — Actor B (Comercial, Pagos y Búsqueda Global)

**Proyecto 2 · Grupo 13 SA · INF513 Tecnología Web · UAGRM**
**Responsable:** Persona B (CU3 Comercial, Módulo de Pagos y Búsqueda Global)

---

## 1. Resumen de tareas completadas

| # | Tarea | Estado | Detalle principal |
|---|---|---|---|
| B1 | CU3 Empresas, Visitantes, Servicios, Tarifas y Paquetes | ✅ Hecho | Modelos, Services, FormRequests, Controllers y pantallas React. `PaqueteService` calcula `precio_total` con descuento y sincroniza `paquete_item`. `TarifaService` valida vigencia y monto > 0. |
| B2 | CU3 Proformas y Campañas | ✅ Hecho | `ProformaService` genera correlativo `PRO-AAAA-NNNNN`, calcula subtotal/descuento/total, controla vencimiento (`fecha_validez`), valida permiso `proformas.descuento`, aprobación/rechazo (`proformas.aprobar`) y filtro "solo lo suyo" para Cliente. `CampaniaService` exige proforma aprobada sin campaña previa. |
| B3 | Cotización pública del visitante | ✅ Hecho | Endpoints públicos `GET /api/v1/publico/catalogo` y `POST /api/v1/publico/cotizar` (sin login) + pantalla pública `/cotizar` enlazada desde el Login. Registra/reutiliza `visitante` y crea `proforma` pendiente. |
| B4 | Tablas nuevas de Pagos y trigger | ✅ Hecho | Script idempotente `database/sql/03_pagos.sql` + migración `2026_11_24_000001_pagos_proyecto2.php` con `metodo_pago`, `plan_pago`, `cuota`, `transaccion_pago` y trigger `trg_cuota_pagada`. |
| B5 | Lógica y pantallas de Pagos | ✅ Hecho | `MetodoPagoService` (validación Luhn, expiración, CVV sin almacenar PAN ni CVV), `PagoService` (pasarela simulada: `0002` fondos insuficientes, `0069` vencida, otras aprobadas `AUT-XXXXXX`), pago único, planes de 2 a 24 cuotas, pago de cuota e historial. |
| B6 | Búsqueda global (backend) | ✅ Hecho | `GET /api/v1/buscar?q=` en `BusquedaService` y `BusquedaController`. Busca en empresas, servicios, paquetes, proformas y campañas (máx. 5 por tipo), respetando privilegios RBAC y filtro "solo lo suyo". |
| B7 | Datos de prueba de Pagos | ✅ Hecho | `Database\Seeders\PagosSeeder` (idempotente, registrado en `DatabaseSeeder.php`) con métodos de pago, transacción rechazada/aprobada y plan de 3 cuotas. |
| B8 | Opciones para `<select>` | ✅ Hecho | Agregados en `OpcionesController`: `servicios`, `tarifas`, `paquetes`, `visitantes`, `proformas-sin-campania`, `proformas-pagables`, `metodos-pago` (filtrando por rol Cliente cuando corresponde). |

---

## 2. Tablas nuevas creadas en PostgreSQL (solo aditivas)

Archivo SQL: `database/sql/03_pagos.sql`
Migración Laravel: `database/migrations/2026_11_24_000001_pagos_proyecto2.php`

> No se modifica ninguna de las 27 tablas del Proyecto 1 ni las tablas de seguridad de Persona A. Todas las sentencias usan `CREATE TABLE IF NOT EXISTS` y `CREATE INDEX IF NOT EXISTS`.

| Objeto | Tipo | Descripción |
|---|---|---|
| `metodo_pago` | Tabla | Métodos registrados por usuario (`tarjeta`, `qr`, `billetera`), `marca`, `ultimos4`, `titular`, `mes_exp`, `anio_exp`, `token_simulado`, `predeterminado`, `activo`. **Nunca guarda número completo ni CVV.** |
| `plan_pago` | Tabla | Planes de cuotas (2 a 24 cuotas, frecuencia `mensual` / `quincenal` / `semanal`, estado `activo` / `completado` / `cancelado`) asociados a una `proforma`. |
| `cuota` | Tabla | Cuotas individuales (`nro`, `monto`, `fecha_vencimiento`, `estado`: `pendiente` / `pagada` / `vencida`, `fecha_pago`). |
| `transaccion_pago` | Tabla | Historial de pagos (`aprobada` / `rechazada`, `codigo_autorizacion` `AUT-XXXXXX`, `mensaje`, `monto`, `id_proforma`, `id_cuota`). |
| `trg_cuota_pagada` (`fn_cuota_pagada`) | Trigger | Al pasar una `cuota` a estado `'pagada'`, registra `fecha_pago` y, si ya no quedan cuotas pendientes en el plan, marca `plan_pago.estado = 'completado'`. |

---

## 3. Cómo aplicar las tablas y datos de prueba cuando la BD esté disponible

```bash
php artisan migrate
php artisan db:seed --class=PagosSeeder
```

O ejecutando directamente el script SQL en PostgreSQL:

```bash
psql -h db.tecnoweb.org.bo -U grupo13sa -d db_grupo13sa -f database/sql/03_pagos.sql
```

---

## 4. Archivos creados o modificados por Actor B

### Base de datos y seeders
- `database/sql/03_pagos.sql`
- `database/migrations/2026_11_24_000001_pagos_proyecto2.php`
- `database/seeders/PagosSeeder.php`
- `database/seeders/DatabaseSeeder.php` (registro de `PagosSeeder`)

### Backend (Modelos, Servicios, Requests, Controladores y Rutas)
- Modelos: `EmpresaAnunciante.php` (ampliado), `Visitante.php`, `ServicioPublicitario.php`, `Tarifa.php`, `Paquete.php`, `PaqueteItem.php`, `Proforma.php`, `DetalleProforma.php`, `Campania.php`, `MetodoPago.php`, `PlanPago.php`, `Cuota.php`, `TransaccionPago.php`
- Servicios: `EmpresaService.php`, `VisitanteService.php`, `ServicioService.php`, `TarifaService.php`, `PaqueteService.php`, `ProformaService.php`, `CampaniaService.php`, `MetodoPagoService.php`, `PagoService.php`, `BusquedaService.php`
- FormRequests: `EmpresaRequest.php`, `ServicioRequest.php`, `TarifaRequest.php`, `PaqueteRequest.php`, `ProformaRequest.php`, `EstadoProformaRequest.php`, `CotizacionPublicaRequest.php`, `CampaniaRequest.php`, `MetodoPagoRequest.php`, `PagarProformaRequest.php`, `PlanPagoRequest.php`, `PagarCuotaRequest.php`
- Controladores: `EmpresaController.php`, `VisitanteController.php`, `ServicioController.php`, `TarifaController.php`, `PaqueteController.php`, `ProformaController.php`, `CampaniaController.php`, `MetodoPagoController.php`, `PagoController.php`, `PlanPagoController.php`, `BusquedaController.php`, `OpcionesController.php` (ampliado)
- Rutas y traducciones: `routes/api/comercial.php`, `lang/es/validation.php`

### Frontend (React SPA)
- Comercial: `resources/js/pages/comercial/Empresas.jsx`, `Visitantes.jsx`, `Servicios.jsx`, `Tarifas.jsx`, `Paquetes.jsx`, `Proformas.jsx`, `Campanias.jsx`, `CotizacionPublica.jsx`
- Pagos: `resources/js/pages/pagos/MetodosPago.jsx`, `Pagos.jsx`, `PlanesPago.jsx`
- Rutas y acceso público: `resources/js/rutas.jsx`, `resources/js/app.jsx`, `resources/js/pages/Login.jsx`

