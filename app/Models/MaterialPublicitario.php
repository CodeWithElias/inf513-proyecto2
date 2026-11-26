<?php

namespace App\Models;

use App\Support\ArchivoMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tabla material_publicitario (CU4 - RF08/RF09).
 */
class MaterialPublicitario extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_APROBADO = 'aprobado';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_CORRECCION = 'correccion';
    public const ESTADO_RETIRADO = 'retirado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_APROBADO,
        self::ESTADO_RECHAZADO,
        self::ESTADO_CORRECCION,
        self::ESTADO_RETIRADO,
    ];

    public const TIPOS = ['video', 'audio', 'imagen', 'banner', 'texto'];

    public const FORMATOS_VALIDOS = [
        'video' => ['mp4', 'mov', 'avi', 'mkv'],
        'audio' => ['mp3', 'wav', 'aac', 'ogg'],
        'imagen' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'banner' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'texto' => ['txt', 'pdf', 'docx'],
    ];

    protected $table = 'material_publicitario';
    protected $primaryKey = 'id_material';
    public $timestamps = false;

    protected $fillable = [
        'id_campania',
        'id_usuario',
        'tipo',
        'nombre_archivo',
        'url_archivo',
        'formato',
        'tamano_bytes',
        'duracion_seg',
        'texto_publicacion',
        'version',
        'estado',
        'fecha_carga',
    ];

    protected $casts = [
        'fecha_carga' => 'datetime',
        'version' => 'integer',
        'duracion_seg' => 'integer',
        'tamano_bytes' => 'integer',
    ];

    /** Enlace para ver el material en el navegador (firmado si el archivo está en el servidor). */
    protected $appends = ['url_vista', 'archivo_interno'];

    public function getUrlVistaAttribute(): ?string
    {
        return ArchivoMaterial::urlVista($this);
    }

    /** true si el archivo está guardado en el servidor (subido o de ejemplo), false si es un enlace externo. */
    public function getArchivoInternoAttribute(): bool
    {
        return ArchivoMaterial::esInterno($this->url_archivo);
    }

    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class, 'id_campania', 'id_campania');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionMaterial::class, 'id_material', 'id_material')->orderByDesc('fecha');
    }

    public function ultimaRevision(): HasOne
    {
        return $this->hasOne(RevisionMaterial::class, 'id_material', 'id_material')->latestOfMany('fecha');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function puedeRevisarse(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_CORRECCION], true);
    }

    public function puedeSubirNuevaVersion(): bool
    {
        return $this->estado === self::ESTADO_CORRECCION;
    }

    public function estaRetirado(): bool
    {
        return $this->estado === self::ESTADO_RETIRADO;
    }
}
