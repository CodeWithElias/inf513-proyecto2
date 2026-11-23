<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token de Sanctum. Sanctum actualiza last_used_at en CADA petición (una
 * escritura extra a la BD); aquí solo se guarda si pasaron más de 5 minutos.
 */
class TokenAcceso extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    public function save(array $options = []): bool
    {
        $soloUso = $this->exists && array_keys($this->getDirty()) === ['last_used_at'];
        $anterior = $this->getOriginal('last_used_at');

        if ($soloUso && $anterior && now()->diffInMinutes($anterior, true) < 5) {
            $this->syncOriginal();

            return true;
        }

        return parent::save($options);
    }
}
