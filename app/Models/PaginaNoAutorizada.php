<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaginaNoAutorizada extends Model
{
    public const DETECTADA = 'detectada';
    public const CONFIRMADA = 'confirmada';
    public const DENUNCIADA = 'denunciada';
    public const ELIMINADA = 'eliminada';

    /** Transiciones válidas (igual que NPagina del Proyecto 1). */
    public const TRANSICIONES = [
        self::DETECTADA => [self::CONFIRMADA],
        self::CONFIRMADA => [self::DENUNCIADA],
        self::DENUNCIADA => [self::ELIMINADA],
        self::ELIMINADA => [],
    ];

    protected $table = 'pagina_no_autorizada';
    protected $primaryKey = 'id_pagina';
    public $timestamps = false;
    protected $fillable = ['url', 'nombre_pagina', 'estado', 'fecha_revision', 'id_plataforma', 'id_usuario'];
    protected $casts = ['fecha_revision' => 'datetime'];

    public function plataforma(): BelongsTo
    {
        return $this->belongsTo(Plataforma::class, 'id_plataforma', 'id_plataforma');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function detecciones(): HasMany
    {
        return $this->hasMany(DeteccionPagina::class, 'id_pagina', 'id_pagina');
    }
}
