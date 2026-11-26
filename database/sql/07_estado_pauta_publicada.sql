-- =====================================================================
-- PROYECTO 2 - Estado de pauta emitida = 'publicada' (igual que NPauta del Proyecto 1)
-- Versiones anteriores del Proyecto 2 guardaban 'emitida'; la BD es compartida con el
-- Proyecto 1, que usa 'publicada'. Se unifican para que métricas, reportes y filtros
-- de ambos sistemas vean las mismas pautas. Idempotente.
-- =====================================================================

UPDATE programacion_pauta SET estado = 'publicada' WHERE estado = 'emitida';
