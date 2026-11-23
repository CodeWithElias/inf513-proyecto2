<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tabla usuario (Proyecto 1). La contraseña se guarda en PBKDF2
 * (compatible con el sistema vía correo), ver App\Support\Pbkdf2.
 */
class Usuario extends Authenticatable
{
    use HasApiTokens;

    /** Roles con acceso a todos los registros ("personal" de Canal 11). */
    public const ROLES_PERSONAL = ['Administrador', 'Director'];

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = ['nombre_completo', 'email', 'telefono', 'password_hash', 'activo', 'id_rol', 'id_unidad'];
    protected $hidden = ['password_hash'];
    protected $casts = [
        'activo' => 'boolean',
        'fecha_registro' => 'datetime',
        'ultimo_acceso' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getRememberTokenName(): string
    {
        return ''; // la tabla no tiene remember_token
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadUniversitaria::class, 'id_unidad', 'id_unidad');
    }

    public function representante(): HasOne
    {
        return $this->hasOne(Representante::class, 'id_usuario', 'id_usuario');
    }

    /** Códigos de privilegio del rol (cacheados 10 min; se limpian al editar el rol). */
    public function privilegios(): array
    {
        return Cache::remember(self::clavePrivilegios($this->id_rol), 600, function () {
            return DB::table('rol_privilegio as rp')
                ->join('privilegio as p', 'p.id_privilegio', '=', 'rp.id_privilegio')
                ->where('rp.id_rol', $this->id_rol)
                ->orderBy('p.codigo')
                ->pluck('p.codigo')
                ->all();
        });
    }

    public function tienePrivilegio(string $codigo): bool
    {
        return in_array($codigo, $this->privilegios(), true);
    }

    /** Administrador o Director: ven todo (equivale a Sesion.accesoTotal() del Proyecto 1). */
    public function esPersonal(): bool
    {
        return in_array($this->rol?->nombre, self::ROLES_PERSONAL, true);
    }

    public function esEncargado(): bool
    {
        return $this->rol?->nombre === 'Encargado de Unidad';
    }

    public function esCliente(): bool
    {
        return $this->rol?->nombre === 'Cliente';
    }

    /** id_empresa si el usuario es representante (cliente), o null. */
    public function idEmpresa(): ?int
    {
        return $this->representante?->id_empresa;
    }

    public static function clavePrivilegios(int $idRol): string
    {
        return "privilegios_rol_{$idRol}";
    }
}
