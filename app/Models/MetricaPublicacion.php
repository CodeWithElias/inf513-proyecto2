<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla metrica_publicacion (CU7 - RF13).
 */
class MetricaPublicacion extends Model
{
    protected $table = 'metrica_publicacion';
    protected $primaryKey = 'id_metrica';
    public $timestamps = false;

    protected $fillable = [
        'id_programacion',
        'fecha_medicion',
        'alcance',
        'impresiones',
        'interacciones',
        'clics',
        'compartidos',
    ];

    protected $casts = [
        'fecha_medicion' => 'datetime',
        'alcance' => 'integer',
        'impresiones' => 'integer',
        'interacciones' => 'integer',
        'clics' => 'integer',
        'compartidos' => 'integer',
    ];

    public function pauta(): BelongsTo
    {
        return $this->belongsTo(ProgramacionPauta::class, 'id_programacion', 'id_programacion');
    }
}
