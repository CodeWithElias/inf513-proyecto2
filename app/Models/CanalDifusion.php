<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** canal_difusion: television | radio | web | red_social (generalización de red_social). */
class CanalDifusion extends Model
{
    public const RED_SOCIAL = 'red_social';
    public const TIPOS = ['television', 'radio', 'web'];

    protected $table = 'canal_difusion';
    protected $primaryKey = 'id_canal';
    public $timestamps = false;
    protected $fillable = ['tipo', 'nombre', 'fecha_alta', 'fecha_baja', 'activo'];
    protected $casts = ['activo' => 'boolean', 'fecha_alta' => 'date:Y-m-d', 'fecha_baja' => 'date:Y-m-d'];

    public function redSocial(): HasOne
    {
        return $this->hasOne(RedSocial::class, 'id_canal', 'id_canal');
    }
}
