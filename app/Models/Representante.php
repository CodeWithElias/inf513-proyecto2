<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** representante ES UN usuario (PK = id_usuario) y pertenece a una empresa. */
class Representante extends Model
{
    protected $table = 'representante';
    protected $primaryKey = 'id_usuario';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id_usuario', 'ci', 'cargo', 'id_empresa'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaAnunciante::class, 'id_empresa', 'id_empresa');
    }
}
