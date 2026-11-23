<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ítem del menú dinámico. Se muestra si el rol tiene codigo_privilegio (o si es NULL). */
class MenuItem extends Model
{
    protected $table = 'menu_item';
    protected $primaryKey = 'id_menu';
    public $timestamps = false;
    protected $fillable = ['titulo', 'ruta', 'icono', 'orden', 'id_padre', 'codigo_privilegio', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
