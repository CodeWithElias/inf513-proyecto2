<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Hash de contraseñas del sistema: bcrypt (Hash de Laravel, BCRYPT_ROUNDS en .env).
 *
 * Compatibilidad: el sistema vía correo (Proyecto 1, misma tabla usuario) guarda
 * los usuarios que crea con PBKDF2 ("pbkdf2$..."). Esos hashes se aceptan al
 * iniciar sesión y se convierten a bcrypt automáticamente (ver AuthService).
 */
final class Contrasena
{
    public static function hash(string $password): string
    {
        return Hash::make($password);
    }

    public static function verificar(string $password, ?string $guardado): bool
    {
        if (self::esBcrypt($guardado)) {
            return Hash::check($password, $guardado);
        }
        if (str_starts_with((string) $guardado, 'pbkdf2$')) {
            return Pbkdf2::verificar($password, $guardado);
        }

        return false;
    }

    /** true si el hash es antiguo (PBKDF2) o bcrypt con menos rondas que las configuradas. */
    public static function necesitaActualizar(?string $guardado): bool
    {
        return ! self::esBcrypt($guardado) || Hash::needsRehash($guardado);
    }

    private static function esBcrypt(?string $guardado): bool
    {
        return (bool) preg_match('/^\$2[aby]\$\d{2}\$/', (string) $guardado);
    }
}
