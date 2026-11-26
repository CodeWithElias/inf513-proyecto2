<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El banco pide verificar la tarjeta (3D Secure). Se responde 202 con el client_secret: el navegador
 * muestra la verificación de Stripe y luego reenvía la misma operación con payment_intent (ver bootstrap/app.php).
 * Hasta entonces no se registra nada.
 */
class PagoRequiereAccion extends RuntimeException
{
    public function __construct(public readonly string $clientSecret, public readonly string $paymentIntent)
    {
        parent::__construct('Su banco pide verificar la tarjeta.');
    }
}
