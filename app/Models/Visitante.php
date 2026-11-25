<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitante extends Model
{
    protected $table = 'visitante';
    protected $primaryKey = 'id_visitante';
    public $timestamps = false;
    protected $fillable = ['nombre', 'email', 'telefono'];
    protected $casts = ['fecha_registro' => 'datetime'];

    public function proformas(): HasMany
    {
        return $this->hasMany(Proforma::class, 'id_visitante', 'id_visitante');
    }
}

