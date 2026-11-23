<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** red_social ES UN canal_difusion (PK = id_canal). */
class RedSocial extends Model
{
    protected $table = 'red_social';
    protected $primaryKey = 'id_canal';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id_canal', 'url', 'id_plataforma', 'id_usuario', 'id_unidad'];

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalDifusion::class, 'id_canal', 'id_canal');
    }

    public function plataforma(): BelongsTo
    {
        return $this->belongsTo(Plataforma::class, 'id_plataforma', 'id_plataforma');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadUniversitaria::class, 'id_unidad', 'id_unidad');
    }

    public function seguidores(): HasMany
    {
        return $this->hasMany(SeguidoresRed::class, 'id_canal', 'id_canal');
    }
}
