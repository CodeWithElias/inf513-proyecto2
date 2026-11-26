-- =====================================================================
-- PROYECTO 2 - Ejecución manual del monitoreo (comando ejeScr del Proyecto 1)
-- Agrega el privilegio ejecuciones.crear y lo asigna al Administrador y al Director.
-- Idempotente.
-- =====================================================================

INSERT INTO privilegio (codigo, recurso, accion, descripcion, modulo)
VALUES ('ejecuciones.crear', 'ejecuciones', 'crear', 'Ejecutar el monitoreo de páginas no autorizadas', 'Monitoreo')
ON CONFLICT (codigo) DO UPDATE SET descripcion = EXCLUDED.descripcion, modulo = EXCLUDED.modulo;

INSERT INTO rol_privilegio (id_rol, id_privilegio)
SELECT r.id_rol, p.id_privilegio
FROM rol r
JOIN privilegio p ON p.codigo = 'ejecuciones.crear'
WHERE r.nombre IN ('Administrador', 'Director')
ON CONFLICT DO NOTHING;
