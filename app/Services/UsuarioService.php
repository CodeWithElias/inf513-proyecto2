<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Rol;
use App\Models\UnidadUniversitaria;
use App\Models\Usuario;
use App\Support\Contrasena;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** CU1 - Gestión de usuarios (portado de grupo13.negocio.NUsuario). */
class UsuarioService
{
    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return Usuario::query()
            ->with(['rol:id_rol,nombre', 'unidad:id_unidad,nombre'])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nombre_completo', 'ilike', "%{$v}%")
                ->orWhere('email', 'ilike', "%{$v}%")))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->when($f['id_rol'] ?? null, fn ($q, $v) => $q->where('id_rol', $v))
            ->orderBy('id_usuario')
            ->paginate($porPagina);
    }

    public function crear(array $d): Usuario
    {
        $this->validarRolYUnidad((int) $d['id_rol'], $d['id_unidad'] ?? null);

        return Usuario::create([
            'nombre_completo' => $d['nombre_completo'],
            'email' => mb_strtolower($d['email']),
            'telefono' => $d['telefono'] ?? null,
            'password_hash' => Contrasena::hash($d['password']),
            'id_rol' => $d['id_rol'],
            'id_unidad' => $d['id_unidad'] ?? null,
        ])->load(['rol:id_rol,nombre', 'unidad:id_unidad,nombre']);
    }

    public function actualizar(Usuario $usuario, array $d, Usuario $actor): Usuario
    {
        if ($usuario->id_usuario === $actor->id_usuario && (int) $d['id_rol'] !== $usuario->id_rol) {
            throw new NegocioException('No puede cambiar su propio rol. Pídaselo a otro administrador.');
        }
        $this->validarRolYUnidad((int) $d['id_rol'], $d['id_unidad'] ?? null);
        // Un representante de empresa debe seguir siendo Cliente (si no, vería y operaría como cliente con otro rol)
        if ((int) $d['id_rol'] !== (int) $usuario->id_rol && $usuario->idEmpresa() !== null
            && Rol::whereKey($d['id_rol'])->value('nombre') !== 'Cliente') {
            throw new NegocioException("{$usuario->nombre_completo} es representante de una empresa. Quítelo de «Clientes» antes de cambiarle el rol.");
        }

        $usuario->fill([
            'nombre_completo' => $d['nombre_completo'],
            'email' => mb_strtolower($d['email']),
            'telefono' => $d['telefono'] ?? null,
            'id_rol' => $d['id_rol'],
            'id_unidad' => $d['id_unidad'] ?? null,
        ]);
        if (! empty($d['password'])) {
            $usuario->password_hash = Contrasena::hash($d['password']);
        }
        $usuario->save();

        return $usuario->load(['rol:id_rol,nombre', 'unidad:id_unidad,nombre']);
    }

    /** Baja lógica o reactivación. */
    public function cambiarEstado(Usuario $usuario, bool $activo, Usuario $actor): Usuario
    {
        if ($usuario->activo === $activo) {
            throw new NegocioException($activo
                ? "El usuario {$usuario->nombre_completo} ya está activo."
                : "El usuario {$usuario->nombre_completo} ya está dado de baja.");
        }
        if (! $activo && $usuario->id_usuario === $actor->id_usuario) {
            throw new NegocioException('No puede dar de baja su propia cuenta.');
        }
        $usuario->update(['activo' => $activo]);
        if (! $activo) {
            $usuario->tokens()->delete(); // cierra sus sesiones abiertas
        }

        return $usuario;
    }

    private function validarRolYUnidad(int $idRol, mixed $idUnidad): void
    {
        $rol = Rol::find($idRol);
        if (! $rol) {
            throw new NegocioException('El rol seleccionado no existe.');
        }
        if (! $rol->activo) {
            throw new NegocioException("El rol {$rol->nombre} está inactivo.");
        }
        if ($idUnidad !== null && $idUnidad !== '') {
            $unidad = UnidadUniversitaria::find($idUnidad);
            if (! $unidad) {
                throw new NegocioException('La unidad seleccionada no existe.');
            }
            if (! $unidad->activo) {
                throw new NegocioException("La unidad {$unidad->nombre} está inactiva.");
            }
        }
    }
}
