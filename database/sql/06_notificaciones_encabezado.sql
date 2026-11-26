-- =====================================================================
-- PROYECTO 2 - Notificaciones en el encabezado
-- Las notificaciones se muestran con la campana del encabezado (con el
-- número de no leídas), así que se quita la opción del menú lateral.
-- Se DESACTIVA (no se borra): la pantalla /notificaciones sigue existiendo
-- y se abre desde "Ver todas" de la campana. Idempotente.
-- =====================================================================

UPDATE menu_item SET activo = FALSE WHERE ruta = '/notificaciones' AND activo;
