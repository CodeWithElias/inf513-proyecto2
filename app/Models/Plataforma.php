<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plataforma extends Model
{
    protected $table = 'plataforma';
    protected $primaryKey = 'id_plataforma';
    public $timestamps = false;
    protected $fillable = ['nombre'];
}
