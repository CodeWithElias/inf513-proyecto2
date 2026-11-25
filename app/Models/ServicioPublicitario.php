<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicioPublicitario extends Model
{
    public const TIPOS = ['television', 'radio', 'redes_sociales', 'web', 'produccion', 'otro'];

    protected $table = 'servicio_publicitario';
    protected $primaryKey = 'id_servicio';
    public $timestamps = false;
    protected $fillable = ['nombre', 'tipo', 'descripcion', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function tarifas(): HasMany
    {
        return $this->hasMany(Tarifa::class, 'id_servicio', 'id_servicio');
    }
}

