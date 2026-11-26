<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tabla usuario (Proyecto 1). La contraseña se guarda con bcrypt
 * (ver App\Support\Contrasena); los hashes PBKDF2 antiguos se aceptan y se convierten al iniciar sesión.
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

    /** Si la cuenta nació de una solicitud de visitante aprobada: su id_visitante, o null. */
    public function idVisitante(): ?int
    {
        if (! $this->visitanteCargado) {
            $this->visitanteCargado = true;
            $id = DB::table('solicitud_cuenta')
                ->where('id_usuario', $this->id_usuario)
                ->where('estado', SolicitudCuenta::APROBADA)
                ->value('id_visitante');
            $this->idVisitanteCache = $id === null ? null : (int) $id;
        }

        return $this->idVisitanteCache;
    }

    // Memoria por petición de idVisitante() (no son columnas)
    private bool $visitanteCargado = false;
    private ?int $idVisitanteCache = null;

    /**
     * "Solo lo suyo" para proformas del Cliente: las de su empresa y las que hizo como visitante
     * antes de tener cuenta. $prefijo es el alias de la tabla proforma en la consulta (ej. 'p.').
     */
    public function filtrarProformas($consulta, string $prefijo = ''): void
    {
        $idEmpresa = $this->idEmpresa();
        $idVisitante = $this->idVisitante();
        $consulta->where(function ($w) use ($prefijo, $idEmpresa, $idVisitante) {
            $w->where("{$prefijo}id_empresa", $idEmpresa ?? 0);
            if ($idVisitante) {
                $w->orWhere("{$prefijo}id_visitante", $idVisitante);
            }
        });
    }

    /** ¿La proforma es del cliente (de su empresa o de cuando era visitante)? */
    public function esSuProforma(?Proforma $proforma): bool
    {
        if (! $proforma) {
            return false;
        }

        return ($this->idEmpresa() !== null && $proforma->id_empresa === $this->idEmpresa())
            || ($this->idVisitante() !== null && $proforma->id_visitante === $this->idVisitante());
    }

    public static function clavePrivilegios(int $idRol): string
    {
        return "privilegios_rol_{$idRol}";
    }
}
