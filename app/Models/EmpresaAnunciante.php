<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modelo creado por Persona A (lo usa Representante). Persona B lo amplía en CU3. */
class EmpresaAnunciante extends Model
{
    protected $table = 'empresa_anunciante';
    protected $primaryKey = 'id_empresa';
    public $timestamps = false;
    protected $fillable = ['razon_social', 'nit', 'nombre_comercial', 'direccion_facturacion',
        'email_facturacion', 'telefono', 'activo'];
    protected $casts = ['activo' => 'boolean', 'fecha_registro' => 'datetime'];

    public function representantes(): HasMany
    {
        return $this->hasMany(Representante::class, 'id_empresa', 'id_empresa');
    }

    public function proformas(): HasMany
    {
        return $this->hasMany(Proforma::class, 'id_empresa', 'id_empresa');
    }
}
