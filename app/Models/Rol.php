<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;
    protected $fillable = ['nombre', 'descripcion', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_rol', 'id_rol');
    }

    public function privilegios(): BelongsToMany
    {
        return $this->belongsToMany(Privilegio::class, 'rol_privilegio', 'id_rol', 'id_privilegio');
    }
}
