<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** CU1 - Roles y matriz de privilegios (portado de NRol + RBAC del Proyecto 2). */
class RolService
{
    /** Roles que usa el código por nombre: no se pueden renombrar. */
    public const ROLES_BASE = ['Administrador', 'Director', 'Encargado de Unidad', 'Cliente'];

    public function listar(?string $q): Collection
    {
        return Rol::query()
            ->withCount([
                'usuarios as usuarios_activos' => fn ($w) => $w->where('activo', true),
                'privilegios',
            ])
            ->when($q, fn ($w, $v) => $w->where('nombre', 'ilike', "%{$v}%"))
            ->orderBy('id_rol')
            ->get();
    }

    public function crear(array $d): Rol
    {
        return Rol::create(['nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] ?? null]);
    }

    public function actualizar(Rol $rol, array $d): Rol
    {
        if (in_array($rol->nombre, self::ROLES_BASE, true) && $d['nombre'] !== $rol->nombre) {
            throw new NegocioException("El rol {$rol->nombre} es un rol base del sistema y no se puede renombrar.");
        }
        $rol->update(['nombre' => $d['nombre'], 'descripcion' => $d['descripcion'] ?? null]);

        return $rol;
    }

    public function cambiarEstado(Rol $rol, bool $activo): Rol
    {
        if ($rol->activo === $activo) {
            throw new NegocioException($activo ? "El rol {$rol->nombre} ya está activo." : "El rol {$rol->nombre} ya está dado de baja.");
        }
        if (! $activo) {
            if ($rol->nombre === 'Administrador') {
                throw new NegocioException('El rol Administrador no se puede dar de baja.');
            }
            $usuarios = $rol->usuarios()->where('activo', true)->count();
            if ($usuarios > 0) {
                throw new NegocioException("No se puede dar de baja el rol {$rol->nombre}: tiene {$usuarios} usuario(s) activo(s). Reasígnelos o déles de baja primero.");
            }
        }
        $rol->update(['activo' => $activo]);

        return $rol;
    }

    /** Matriz: todos los privilegios agrupados por módulo, marcando los asignados al rol. */
    public function matriz(Rol $rol): array
    {
        $asignados = $rol->privilegios()->pluck('privilegio.id_privilegio')->flip();

        return Privilegio::orderBy('modulo')->orderBy('recurso')->orderBy('id_privilegio')->get()
            ->groupBy('modulo')
            ->map(fn ($lista, $modulo) => [
                'modulo' => $modulo,
                'privilegios' => $lista->map(fn ($p) => [
                    'id_privilegio' => $p->id_privilegio,
                    'codigo' => $p->codigo,
                    'recurso' => $p->recurso,
                    'accion' => $p->accion,
                    'descripcion' => $p->descripcion,
                    'asignado' => $asignados->has($p->id_privilegio),
                ])->values(),
            ])->values()->all();
    }

    public function asignarPrivilegios(Rol $rol, array $ids): void
    {
        if ($rol->nombre === 'Administrador') {
            $criticos = Privilegio::whereIn('codigo', ['roles.ver', 'roles.permisos'])->pluck('id_privilegio')->all();
            if (array_diff($criticos, $ids) !== []) {
                throw new NegocioException('Al rol Administrador no se le pueden quitar los permisos para ver roles y asignar permisos (quedaría sin acceso a esta pantalla).');
            }
        }
        DB::transaction(fn () => $rol->privilegios()->sync($ids));
        Cache::forget(Usuario::clavePrivilegios($rol->id_rol));
    }
}
