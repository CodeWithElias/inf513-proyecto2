<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio no cumplida (equivale a grupo13.negocio.NegocioException).
 * Se responde como HTTP 422 con { "message": "..." } (ver bootstrap/app.php).
 * El mensaje debe estar redactado en español y orientado al usuario.
 */
class NegocioException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
