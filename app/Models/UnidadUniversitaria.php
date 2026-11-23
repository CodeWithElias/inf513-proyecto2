<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadUniversitaria extends Model
{
    protected $table = 'unidad_universitaria';
    protected $primaryKey = 'id_unidad';
    public $timestamps = false;
    protected $fillable = ['nombre', 'tipo', 'activo', 'id_unidad_padre'];
    protected $casts = ['activo' => 'boolean'];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_unidad_padre', 'id_unidad');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'id_unidad_padre', 'id_unidad');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_unidad', 'id_unidad');
    }
}
