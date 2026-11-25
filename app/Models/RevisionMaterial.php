<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla revision_material (CU4 - RF09).
 */
class RevisionMaterial extends Model
{
    public const RESULTADO_APROBADO = 'aprobado';
    public const RESULTADO_RECHAZADO = 'rechazado';
    public const RESULTADO_CORRECCION = 'correccion';

    public const RESULTADOS = [
        self::RESULTADO_APROBADO,
        self::RESULTADO_RECHAZADO,
        self::RESULTADO_CORRECCION,
    ];

    protected $table = 'revision_material';
    protected $primaryKey = 'id_revision';
    public $timestamps = false;

    protected $fillable = [
        'id_material',
        'id_usuario',
        'fecha',
        'resultado',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialPublicitario::class, 'id_material', 'id_material');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
