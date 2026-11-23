<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminoMonitoreo extends Model
{
    protected $table = 'termino_monitoreo';
    protected $primaryKey = 'id_termino';
    public $timestamps = false;
    protected $fillable = ['termino', 'tipo', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
