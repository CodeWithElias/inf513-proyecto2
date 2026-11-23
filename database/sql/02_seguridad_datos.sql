-- =====================================================================
-- PROYECTO 2 - Datos iniciales de seguridad: privilegios, asignación a
-- roles (derivada de la matriz del Proyecto 1) y menú dinámico.
-- Idempotente: los privilegios nuevos se agregan; las asignaciones de
-- roles y el menú solo se cargan si todavía están vacíos, para no pisar
-- los cambios hechos desde la pantalla "Roles y permisos".
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Catálogo de privilegios  (codigo = recurso.accion)
-- ---------------------------------------------------------------------
WITH recursos (recurso, nombre, modulo, acciones) AS (
    VALUES
    -- Seguridad y organización (CU1)
    ('usuarios',       'usuarios',                 'Seguridad',          ARRAY['ver','crear','editar','eliminar']),
    ('roles',          'roles',                    'Seguridad',          ARRAY['ver','crear','editar','eliminar','permisos']),
    ('bitacora',       'la bitácora',              'Seguridad',          ARRAY['ver']),
    ('unidades',       'unidades universitarias',  'Organización',       ARRAY['ver','crear','editar','eliminar']),
    ('representantes', 'clientes (representantes)','Organización',       ARRAY['ver','crear','editar','eliminar']),
    -- Redes sociales (CU2)
    ('plataformas',    'plataformas',              'Redes sociales',     ARRAY['ver','crear']),
    ('canales',        'canales de difusión',      'Redes sociales',     ARRAY['ver','crear','eliminar']),
    ('redes',          'redes sociales',           'Redes sociales',     ARRAY['ver','crear','editar','eliminar']),
    ('seguidores',     'métricas de seguidores',   'Redes sociales',     ARRAY['ver','crear']),
    -- Comercial (CU3)
    ('empresas',       'empresas anunciantes',     'Comercial',          ARRAY['ver','crear','editar','eliminar']),
    ('visitantes',     'visitantes',               'Comercial',          ARRAY['ver']),
    ('servicios',      'servicios publicitarios',  'Comercial',          ARRAY['ver','crear','editar','eliminar']),
    ('tarifas',        'tarifas',                  'Comercial',          ARRAY['ver','crear','editar','eliminar']),
    ('paquetes',       'paquetes',                 'Comercial',          ARRAY['ver','crear','editar','eliminar']),
    ('proformas',      'proformas',                'Comercial',          ARRAY['ver','crear','editar','eliminar','aprobar','descuento']),
    ('campanias',      'campañas',                 'Comercial',          ARRAY['ver','crear','editar']),
    -- Material (CU4)
    ('materiales',     'material publicitario',    'Material',           ARRAY['ver','crear','editar','eliminar']),
    ('revisiones',     'revisiones de material',   'Material',           ARRAY['ver','crear']),
    -- Pauta (CU5)
    ('espacios',       'espacios publicitarios',   'Pauta',              ARRAY['ver','crear','editar','eliminar']),
    ('pautas',         'pautas',                   'Pauta',              ARRAY['ver','crear','editar','eliminar']),
    ('notificaciones', 'notificaciones',           'Pauta',              ARRAY['ver','editar']),
    -- Monitoreo (CU6)
    ('terminos',       'términos de monitoreo',    'Monitoreo',          ARRAY['ver','crear','eliminar']),
    ('paginas',        'páginas no autorizadas',   'Monitoreo',          ARRAY['ver','crear','editar']),
    ('ejecuciones',    'ejecuciones de monitoreo', 'Monitoreo',          ARRAY['ver']),
    -- Métricas y reportes (CU7, CU8)
    ('metricas',       'métricas de publicación',  'Métricas y reportes',ARRAY['ver','crear']),
    ('analisis',       'análisis de impacto',      'Métricas y reportes',ARRAY['ver']),
    ('reportes',       'reportes',                 'Métricas y reportes',ARRAY['ver']),
    ('dashboard',      'el panel de estadísticas', 'Métricas y reportes',ARRAY['ver']),
    -- Pagos
    ('metodos_pago',   'métodos de pago',          'Pagos',              ARRAY['ver','crear','editar','eliminar']),
    ('pagos',          'pagos',                    'Pagos',              ARRAY['ver','crear']),
    ('planes_pago',    'planes de pago',           'Pagos',              ARRAY['ver','crear'])
),
verbos (accion, verbo) AS (
    VALUES ('ver','Ver'), ('crear','Registrar'), ('editar','Modificar'),
           ('eliminar','Dar de baja o reactivar'), ('permisos','Asignar permisos a'),
           ('aprobar','Aprobar o rechazar'), ('descuento','Aplicar descuentos a')
)
INSERT INTO privilegio (codigo, recurso, accion, descripcion, modulo)
SELECT r.recurso || '.' || a.accion, r.recurso, a.accion, v.verbo || ' ' || r.nombre, r.modulo
FROM recursos r
CROSS JOIN LATERAL unnest(r.acciones) AS a(accion)
JOIN verbos v ON v.accion = a.accion
ON CONFLICT (codigo) DO UPDATE
    SET descripcion = EXCLUDED.descripcion, modulo = EXCLUDED.modulo;

-- ---------------------------------------------------------------------
-- 2. Asignación a roles
-- ---------------------------------------------------------------------

-- Administrador: siempre todos los privilegios (también los nuevos).
INSERT INTO rol_privilegio (id_rol, id_privilegio)
SELECT r.id_rol, p.id_privilegio
FROM rol r CROSS JOIN privilegio p
WHERE r.nombre = 'Administrador'
ON CONFLICT DO NOTHING;

-- Roles de negocio: solo si el rol todavía no tiene asignaciones.
WITH matriz (rol, codigos) AS (
    VALUES
    -- Director: todo menos administrar usuarios, roles y la bitácora
    ('Director', NULL::text[]),
    -- Encargado de Unidad: sus redes, su pauta y sus avisos
    ('Encargado de Unidad', ARRAY[
        'redes.ver','seguidores.ver','seguidores.crear','plataformas.ver',
        'pautas.ver','espacios.ver','analisis.ver','metricas.ver',
        'notificaciones.ver','notificaciones.editar']),
    -- Cliente: catálogo, cotizar, su material, su pauta, su impacto y sus pagos
    ('Cliente', ARRAY[
        'servicios.ver','tarifas.ver','paquetes.ver',
        'proformas.ver','proformas.crear','proformas.editar','proformas.aprobar',
        'empresas.ver','campanias.ver',
        'materiales.ver','materiales.crear','materiales.editar','materiales.eliminar','revisiones.ver',
        'pautas.ver','metricas.ver','analisis.ver','reportes.ver',
        'notificaciones.ver','notificaciones.editar',
        'metodos_pago.ver','metodos_pago.crear','metodos_pago.editar','metodos_pago.eliminar',
        'pagos.ver','pagos.crear','planes_pago.ver','planes_pago.crear'])
)
INSERT INTO rol_privilegio (id_rol, id_privilegio)
SELECT r.id_rol, p.id_privilegio
FROM matriz m
JOIN rol r ON r.nombre = m.rol
JOIN privilegio p ON (
        m.codigos IS NULL
        AND p.recurso NOT IN ('bitacora', 'metodos_pago')
        AND p.codigo NOT IN ('usuarios.crear','usuarios.editar','usuarios.eliminar',
                             'roles.crear','roles.editar','roles.eliminar','roles.permisos')
    ) OR p.codigo = ANY (m.codigos)
WHERE NOT EXISTS (SELECT 1 FROM rol_privilegio x WHERE x.id_rol = r.id_rol)
ON CONFLICT DO NOTHING;

-- ---------------------------------------------------------------------
-- 3. Menú dinámico (solo si está vacío)
-- ---------------------------------------------------------------------
DO $$
DECLARE
    g INTEGER;
BEGIN
    IF EXISTS (SELECT 1 FROM menu_item) THEN
        RETURN;
    END IF;

    INSERT INTO menu_item (titulo, ruta, icono, orden, codigo_privilegio) VALUES
        ('Inicio', '/', 'Home', 1, NULL),
        ('Panel de estadísticas', '/dashboard', 'LayoutDashboard', 2, 'dashboard.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Seguridad', 'Shield', 10) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Usuarios',          '/usuarios', 'Users',      1, g, 'usuarios.ver'),
        ('Roles y permisos',  '/roles',    'KeyRound',   2, g, 'roles.ver'),
        ('Bitácora',          '/bitacora', 'ScrollText', 3, g, 'bitacora.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Organización', 'Building2', 20) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Unidades universitarias', '/unidades',       'Network',    1, g, 'unidades.ver'),
        ('Clientes',                '/representantes', 'UserSquare', 2, g, 'representantes.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Redes sociales', 'Share2', 30) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Redes institucionales', '/redes',       'Share2',            1, g, 'redes.ver'),
        ('Plataformas',           '/plataformas', 'MonitorSmartphone', 2, g, 'plataformas.ver'),
        ('Canales de difusión',   '/canales',     'Radio',             3, g, 'canales.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Comercial', 'Briefcase', 40) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Empresas',   '/empresas',   'Factory',         1, g, 'empresas.ver'),
        ('Visitantes', '/visitantes', 'UserRound',       2, g, 'visitantes.ver'),
        ('Servicios',  '/servicios',  'Megaphone',       3, g, 'servicios.ver'),
        ('Tarifas',    '/tarifas',    'BadgeDollarSign', 4, g, 'tarifas.ver'),
        ('Paquetes',   '/paquetes',   'Package',         5, g, 'paquetes.ver'),
        ('Proformas',  '/proformas',  'FileText',        6, g, 'proformas.ver'),
        ('Campañas',   '/campanias',  'Flag',            7, g, 'campanias.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Material y pauta', 'Film', 50) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Material publicitario', '/materiales',     'Film',          1, g, 'materiales.ver'),
        ('Espacios',              '/espacios',       'CalendarDays',  2, g, 'espacios.ver'),
        ('Pautas',                '/pautas',         'CalendarClock', 3, g, 'pautas.ver'),
        ('Notificaciones',        '/notificaciones', 'Bell',          4, g, 'notificaciones.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Monitoreo', 'Radar', 60) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Términos',               '/terminos',    'Search',        1, g, 'terminos.ver'),
        ('Páginas no autorizadas', '/paginas',     'AlertTriangle', 2, g, 'paginas.ver'),
        ('Ejecuciones',            '/ejecuciones', 'PlayCircle',    3, g, 'ejecuciones.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Métricas y reportes', 'BarChart3', 70) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Métricas', '/metricas', 'LineChart',    1, g, 'metricas.ver'),
        ('Reportes', '/reportes', 'FileBarChart', 2, g, 'reportes.ver');

    INSERT INTO menu_item (titulo, icono, orden) VALUES ('Pagos', 'CreditCard', 80) RETURNING id_menu INTO g;
    INSERT INTO menu_item (titulo, ruta, icono, orden, id_padre, codigo_privilegio) VALUES
        ('Métodos de pago', '/metodos-pago', 'Wallet',     1, g, 'metodos_pago.ver'),
        ('Pagos',           '/pagos',        'Receipt',    2, g, 'pagos.ver'),
        ('Planes de pago',  '/planes-pago',  'ListChecks', 3, g, 'planes_pago.ver');
END;
$$;
