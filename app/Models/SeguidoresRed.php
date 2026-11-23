<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguidoresRed extends Model
{
    protected $table = 'seguidores_red';
    protected $primaryKey = 'id_seguidores';
    public $timestamps = false;
    protected $fillable = ['seguidores', 'fecha', 'id_canal'];
    protected $casts = ['seguidores' => 'integer', 'fecha' => 'date:Y-m-d'];
}
