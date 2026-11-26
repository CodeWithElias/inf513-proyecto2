<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Proforma;
use App\Models\SolicitudCuenta;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Avisos del flujo de contratación (campana del encabezado).
 * Cada paso avisa a quien debe dar el siguiente:
 *   proforma → aprobación → pago → material → revisión → pauta → emisión.
 * Destinatarios:
 *   - personal(privilegio): Administradores y Directores activos que tienen ese privilegio.
 *   - cliente(proforma): representantes de la empresa y, si la proforma nació de un visitante,
 *     la cuenta que se le creó al aprobar su solicitud.
 */
class AvisoService
{
    /** @param  int[]  $usuarios */
    public function enviar(array $usuarios, string $titulo, string $mensaje, ?int $idProgramacion = null, ?Usuario $excepto = null): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $usuarios))));
        if ($excepto) {
            $ids = array_values(array_diff($ids, [(int) $excepto->id_usuario]));
        }
        foreach ($ids as $id) {
            Notificacion::create([
                'id_usuario' => $id,
                'id_programacion' => $idProgramacion,
                'medio' => Notificacion::MEDIO_SISTEMA,
                'titulo' => mb_substr($titulo, 0, 150),
                'mensaje' => $mensaje,
                'leida' => false,
                'fecha_envio' => now(),
            ]);
        }

        return count($ids);
    }

    /** Administradores y Directores activos con el privilegio indicado. @return int[] */
    public function personal(string $privilegio): array
    {
        return DB::table('usuario as u')
            ->join('rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->join('rol_privilegio as rp', 'rp.id_rol', '=', 'r.id_rol')
            ->join('privilegio as p', 'p.id_privilegio', '=', 'rp.id_privilegio')
            ->where('u.activo', true)
            ->where('r.activo', true)
            ->whereIn('r.nombre', Usuario::ROLES_PERSONAL)
            ->where('p.codigo', $privilegio)
            ->pluck('u.id_usuario')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Cuentas del cliente dueño de la proforma. @return int[] */
    public function cliente(?Proforma $proforma): array
    {
        if (! $proforma) {
            return [];
        }
        $ids = [];
        if ($proforma->id_empresa) {
            $ids = DB::table('representante as r')
                ->join('usuario as u', 'u.id_usuario', '=', 'r.id_usuario')
                ->where('r.id_empresa', $proforma->id_empresa)
                ->where('u.activo', true)
                ->pluck('u.id_usuario')->all();
        }
        if ($proforma->id_visitante) {
            $ids = array_merge($ids, DB::table('solicitud_cuenta')
                ->where('id_visitante', $proforma->id_visitante)
                ->where('estado', SolicitudCuenta::APROBADA)
                ->whereNotNull('id_usuario')
                ->pluck('id_usuario')->all());
        }

        return array_map('intval', $ids);
    }

    public function alPersonal(string $privilegio, string $titulo, string $mensaje, ?Usuario $excepto = null, ?int $idProgramacion = null): int
    {
        return $this->enviar($this->personal($privilegio), $titulo, $mensaje, $idProgramacion, $excepto);
    }

    public function alCliente(?Proforma $proforma, string $titulo, string $mensaje, ?Usuario $excepto = null, ?int $idProgramacion = null): int
    {
        return $this->enviar($this->cliente($proforma), $titulo, $mensaje, $idProgramacion, $excepto);
    }
}
