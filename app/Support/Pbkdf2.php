<?php

namespace App\Support;

/**
 * SOLO para verificar hashes antiguos. El sistema usa bcrypt (App\Support\Contrasena);
 * esta clase se mantiene porque el sistema vía correo sigue creando usuarios con PBKDF2.
 *
 * Hash de contraseñas compatible con el Proyecto 1 (grupo13.negocio.Seguridad):
 * PBKDF2WithHmacSHA256, 256 bits, sal de 16 bytes.
 * Formato: pbkdf2$iteraciones$salBase64$hashBase64
 *
 * Se mantiene este formato porque la tabla usuario es compartida con el
 * sistema vía correo: un usuario creado en la web debe poder usarse allá.
 */
final class Pbkdf2
{
    private const ITERACIONES = 120000;
    private const BYTES = 32;

    public static function hash(string $password): string
    {
        $sal = random_bytes(16);
        $hash = hash_pbkdf2('sha256', $password, $sal, self::ITERACIONES, self::BYTES, true);

        return 'pbkdf2$'.self::ITERACIONES.'$'.base64_encode($sal).'$'.base64_encode($hash);
    }

    public static function verificar(string $password, ?string $guardado): bool
    {
        $partes = explode('$', (string) $guardado);
        if (count($partes) !== 4 || $partes[0] !== 'pbkdf2' || ! ctype_digit($partes[1])) {
            return false;
        }
        $sal = base64_decode($partes[2], true);
        $esperado = base64_decode($partes[3], true);
        if ($sal === false || $esperado === false) {
            return false;
        }
        $actual = hash_pbkdf2('sha256', $password, $sal, (int) $partes[1], strlen($esperado), true);

        return hash_equals($esperado, $actual);
    }
}
