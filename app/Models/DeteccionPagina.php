<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeteccionPagina extends Model
{
    protected $table = 'deteccion_pagina';
    protected $primaryKey = 'id_deteccion';
    public $timestamps = false;
    protected $fillable = ['evidencia', 'fecha_deteccion', 'id_pagina', 'id_termino', 'id_ejecucion'];
    protected $casts = ['fecha_deteccion' => 'datetime'];

    public function termino(): BelongsTo
    {
        return $this->belongsTo(TerminoMonitoreo::class, 'id_termino', 'id_termino');
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(EjecucionScraping::class, 'id_ejecucion', 'id_ejecucion');
    }
}
