<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaqueteItem extends Model
{
    protected $table = 'paquete_item';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id_paquete', 'id_tarifa', 'cantidad'];
    protected $casts = ['cantidad' => 'integer'];

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(Paquete::class, 'id_paquete', 'id_paquete');
    }

    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class, 'id_tarifa', 'id_tarifa');
    }
}

