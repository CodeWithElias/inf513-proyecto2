<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Representante;
use App\Models\Usuario;
use App\Support\Contrasena;
use Illuminate\Support\Facades\DB;

/** "Mi perfil": datos propios, contraseña y, para el Cliente, registrar su empresa. */
class PerfilService
{
    public function __construct(private readonly EmpresaService $empresas)
    {
    }

    public function actualizar(Usuario $u, array $d): void
    {
        $u->update(['nombre_completo' => $d['nombre_completo'], 'telefono' => $d['telefono'] ?? null]);
    }

    public function cambiarPassword(Usuario $u, string $actual, string $nueva): void
    {
        if (! Contrasena::verificar($actual, $u->password_hash)) {
            throw new NegocioException('La contraseña actual no es correcta.');
        }
        $u->update(['password_hash' => Contrasena::hash($nueva)]);
        // Cierra las demás sesiones abiertas; la actual sigue activa.
        $u->tokens()->where('id', '<>', $u->currentAccessToken()?->id)->delete();
    }

    public function miEmpresa(Usuario $u): ?Representante
    {
        return Representante::with('empresa')->find($u->id_usuario);
    }

    /**
     * El cliente registra su empresa y queda como su representante. Las proformas que generó
     * como visitante pasan a estar también a nombre de la empresa (no pierde nada).
     */
    public function registrarEmpresa(Usuario $u, array $d): Representante
    {
        if (! $u->esCliente()) {
            throw new NegocioException('Solo los clientes pueden registrar su empresa desde su perfil.', 403);
        }
        if (Representante::find($u->id_usuario)) {
            throw new NegocioException('Usted ya es representante de una empresa. Si necesita cambiarla, comuníquese con Canal 11.');
        }

        return DB::transaction(function () use ($u, $d) {
            $empresa = $this->empresas->crear($d); // valida que el NIT no exista
            $representante = Representante::create([
                'id_usuario' => $u->id_usuario,
                'ci' => $d['ci'],
                'cargo' => $d['cargo'] ?? null,
                'id_empresa' => $empresa->id_empresa,
            ]);
            if ($idVisitante = $u->idVisitante()) {
                DB::table('proforma')->where('id_visitante', $idVisitante)->whereNull('id_empresa')
                    ->update(['id_empresa' => $empresa->id_empresa]);
            }

            return $representante->load('empresa');
        });
    }
}
