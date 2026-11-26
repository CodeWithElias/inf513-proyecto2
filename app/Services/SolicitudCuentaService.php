<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Rol;
use App\Models\SolicitudCuenta;
use App\Models\Usuario;
use App\Models\Visitante;
use App\Support\Contrasena;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Visitante → Cliente.
 *  1. Al cotizar, el visitante puede pedir una cuenta eligiendo su contraseña (queda "pendiente").
 *  2. El Director o el Administrador la aprueba: se crea su usuario con rol Cliente
 *     (con la contraseña que eligió; el personal nunca la conoce) o la rechaza con un motivo.
 *  3. El nuevo cliente sigue viendo las proformas que hizo como visitante (Usuario::filtrarProformas).
 */
class SolicitudCuentaService
{
    private const ROLES_REVISORES = ['Administrador', 'Director'];

    /** Antes de crear nada: el correo no puede ser ya de un usuario. */
    public function validarCorreoDisponible(string $email): void
    {
        if (Usuario::whereRaw('lower(email) = lower(?)', [$email])->exists()) {
            throw new NegocioException('Ya existe una cuenta con este correo. Inicie sesión para ver sus proformas, o desmarque «Quiero una cuenta».');
        }
    }

    /** Registra la solicitud (o mantiene la pendiente que ya tenía) y avisa al personal. */
    public function registrar(Visitante $visitante, string $password): array
    {
        $pendiente = SolicitudCuenta::where('id_visitante', $visitante->id_visitante)
            ->where('estado', SolicitudCuenta::PENDIENTE)->first();
        if ($pendiente) {
            // Ya la había pedido: se actualiza la contraseña elegida, sin duplicar ni volver a avisar.
            $pendiente->update(['password_hash' => Contrasena::hash($password)]);

            return ['estado' => 'pendiente', 'nueva' => false];
        }

        SolicitudCuenta::create([
            'id_visitante' => $visitante->id_visitante,
            'password_hash' => Contrasena::hash($password),
            'estado' => SolicitudCuenta::PENDIENTE,
        ]);
        $this->avisarPersonal($visitante);

        return ['estado' => 'pendiente', 'nueva' => true];
    }

    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return SolicitudCuenta::query()
            ->with([
                'visitante:id_visitante,nombre,email,telefono,fecha_registro',
                'usuario:id_usuario,nombre_completo,email',
                'revisor:id_usuario,nombre_completo',
            ])
            ->addSelect(['proformas' => DB::table('proforma')->selectRaw('count(*)')->whereColumn('proforma.id_visitante', 'solicitud_cuenta.id_visitante')])
            ->addSelect(['total_cotizado' => DB::table('proforma')->selectRaw('coalesce(sum(total), 0)')->whereColumn('proforma.id_visitante', 'solicitud_cuenta.id_visitante')])
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->whereHas('visitante', fn ($w) => $w
                ->where('nombre', 'ilike', "%{$v}%")->orWhere('email', 'ilike', "%{$v}%")))
            ->orderByRaw("estado = 'pendiente' desc")
            ->orderByDesc('id_solicitud')
            ->paginate($porPagina);
    }

    /** Crea el usuario Cliente con la contraseña que eligió el visitante. */
    public function aprobar(SolicitudCuenta $s, Usuario $revisor): SolicitudCuenta
    {
        $this->exigirPendiente($s);
        $visitante = $s->visitante;
        if (! $visitante?->email) {
            throw new NegocioException('El visitante no tiene correo electrónico; no se le puede crear una cuenta.');
        }
        if (Usuario::whereRaw('lower(email) = lower(?)', [$visitante->email])->exists()) {
            throw new NegocioException("Ya existe un usuario con el correo {$visitante->email}. Rechace esta solicitud indicando que ya tiene cuenta.");
        }
        $rolCliente = Rol::where('nombre', 'Cliente')->where('activo', true)->first();
        if (! $rolCliente) {
            throw new NegocioException('El rol Cliente no existe o está inactivo.');
        }

        return DB::transaction(function () use ($s, $visitante, $rolCliente, $revisor) {
            $usuario = Usuario::create([
                'nombre_completo' => $visitante->nombre,
                'email' => mb_strtolower($visitante->email),
                'telefono' => $visitante->telefono,
                'password_hash' => $s->password_hash,
                'id_rol' => $rolCliente->id_rol,
                'activo' => true,
            ]);
            $s->update([
                'estado' => SolicitudCuenta::APROBADA,
                'id_usuario' => $usuario->id_usuario,
                'id_revisor' => $revisor->id_usuario,
                'fecha_revision' => now(),
                'password_hash' => null, // ya no hace falta guardarla aquí
            ]);
            DB::table('notificacion')->insert([
                'medio' => 'sistema',
                'titulo' => 'Bienvenido a TVU Canal 11',
                'mensaje' => 'Su cuenta fue aprobada. Aquí puede ver las proformas que generó como visitante. Para contratar campañas, registre su empresa en «Mi perfil».',
                'id_usuario' => $usuario->id_usuario,
            ]);

            return $s->load(['visitante', 'usuario:id_usuario,nombre_completo,email', 'revisor:id_usuario,nombre_completo']);
        });
    }

    public function rechazar(SolicitudCuenta $s, string $motivo, Usuario $revisor): SolicitudCuenta
    {
        $this->exigirPendiente($s);
        $s->update([
            'estado' => SolicitudCuenta::RECHAZADA,
            'motivo_rechazo' => $motivo,
            'id_revisor' => $revisor->id_usuario,
            'fecha_revision' => now(),
            'password_hash' => null,
        ]);

        return $s->load(['visitante', 'revisor:id_usuario,nombre_completo']);
    }

    private function exigirPendiente(SolicitudCuenta $s): void
    {
        if ($s->estado !== SolicitudCuenta::PENDIENTE) {
            throw new NegocioException("Esta solicitud ya fue {$s->estado}.");
        }
    }

    private function avisarPersonal(Visitante $v): void
    {
        $revisores = DB::table('usuario as u')->join('rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->whereIn('r.nombre', self::ROLES_REVISORES)->where('u.activo', true)->pluck('u.id_usuario');
        foreach ($revisores as $id) {
            DB::table('notificacion')->insert([
                'medio' => 'sistema',
                'titulo' => 'Nueva solicitud de cuenta',
                'mensaje' => "El visitante {$v->nombre} ({$v->email}) cotizó y pide una cuenta de cliente. Revísela en Comercial → Solicitudes de cuenta.",
                'id_usuario' => $id,
            ]);
        }
    }
}
