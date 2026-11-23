<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Privilegio = recurso.accion (ej. usuarios.crear). Tabla nueva del Proyecto 2. */
class Privilegio extends Model
{
    protected $table = 'privilegio';
    protected $primaryKey = 'id_privilegio';
    public $timestamps = false;
    protected $fillable = ['codigo', 'recurso', 'accion', 'descripcion', 'modulo'];
}
