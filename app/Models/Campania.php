<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campania extends Model
{
    public const ESTADOS = ['activa', 'pausada', 'finalizada', 'cancelada'];

    protected $table = 'campania';
    protected $primaryKey = 'id_campania';
    public $timestamps = false;
    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'id_proforma'];
    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class, 'id_proforma', 'id_proforma');
    }
}

