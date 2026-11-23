<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EjecucionScraping extends Model
{
    protected $table = 'ejecucion_scraping';
    protected $primaryKey = 'id_ejecucion';
    public $timestamps = false;
    protected $fillable = ['fecha_inicio', 'fecha_fin', 'estado', 'mensaje_error', 'id_plataforma'];
    protected $casts = ['fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime'];

    public function plataforma(): BelongsTo
    {
        return $this->belongsTo(Plataforma::class, 'id_plataforma', 'id_plataforma');
    }

    public function detecciones(): HasMany
    {
        return $this->hasMany(DeteccionPagina::class, 'id_ejecucion', 'id_ejecucion');
    }
}
