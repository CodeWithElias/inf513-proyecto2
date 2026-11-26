-- =====================================================================
-- PROYECTO 2 - Pasarela de pagos Stripe (modo de prueba)
-- Las tarjetas se registran con Stripe Elements: el número nunca pasa por nuestro servidor.
-- Se guarda solo lo que Stripe devuelve (ids, marca, últimos 4 dígitos, vencimiento).
--   stripe_cliente:                      cliente (Customer) de Stripe de cada usuario.
--   metodo_pago.stripe_payment_method:   tarjeta guardada en Stripe (pm_…).
--   transaccion_pago.stripe_payment_intent: cobro en Stripe (pi_…); único, así un mismo
--                                        cobro nunca se registra dos veces.
-- No modifica tablas del Proyecto 1 (usuario queda igual). Idempotente.
-- =====================================================================

CREATE TABLE IF NOT EXISTS stripe_cliente (
    id_usuario     INTEGER PRIMARY KEY REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    customer_id    VARCHAR(80) NOT NULL UNIQUE,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE metodo_pago ADD COLUMN IF NOT EXISTS stripe_payment_method VARCHAR(80);
CREATE UNIQUE INDEX IF NOT EXISTS ux_metodo_stripe ON metodo_pago (stripe_payment_method) WHERE stripe_payment_method IS NOT NULL;

ALTER TABLE transaccion_pago ADD COLUMN IF NOT EXISTS stripe_payment_intent VARCHAR(80);
CREATE UNIQUE INDEX IF NOT EXISTS ux_transaccion_stripe ON transaccion_pago (stripe_payment_intent)
    WHERE stripe_payment_intent IS NOT NULL AND estado = 'aprobada';
