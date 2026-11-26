<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revisa la coherencia entre tablas de todos los casos de uso (solo lectura: no cambia nada).
 *   php artisan flujo:verificar
 * Útil después de cargar datos a mano o desde el sistema por correo (Proyecto 1), que comparte la BD.
 */
class VerificarFlujo extends Command
{
    protected $signature = 'flujo:verificar';

    protected $description = 'Revisa que no haya datos inconsistentes u olvidados entre los casos de uso';

    public function handle(): int
    {
        $hoy = now()->toDateString();
        $revisiones = [
            'CU5 · Espacios «ocupado» sin pauta programada ni publicada' => "
                select e.id_espacio as id from espacio_publicitario e
                where e.estado = 'ocupado' and not exists (select 1 from programacion_pauta p
                    where p.id_espacio = e.id_espacio and p.estado in ('programada','publicada'))",
            'CU5 · Pautas programadas cuyo espacio no figura como ocupado' => "
                select p.id_programacion as id from programacion_pauta p join espacio_publicitario e on e.id_espacio = p.id_espacio
                where p.estado = 'programada' and e.estado <> 'ocupado'",
            'CU5 · Pautas programadas en canales dados de baja' => "
                select p.id_programacion as id from programacion_pauta p join espacio_publicitario e on e.id_espacio = p.id_espacio
                join canal_difusion c on c.id_canal = e.id_canal where p.estado = 'programada' and not c.activo",
            'CU5 · Pautas con estado desconocido (p. ej. «emitida» en lugar de «publicada»)' => "
                select id_programacion as id from programacion_pauta where estado not in ('programada','publicada','cancelada')",
            'CU5 · Pautas programadas con fecha pasada sin confirmar emisión' => "
                select p.id_programacion as id from programacion_pauta p join espacio_publicitario e on e.id_espacio = p.id_espacio
                where p.estado = 'programada' and e.fecha < '{$hoy}'",
            'CU3 · Proformas aprobadas sin campaña' => "
                select p.id_proforma as id from proforma p where p.estado = 'aprobada'
                and not exists (select 1 from campania c where c.id_proforma = p.id_proforma)",
            'CU3 · Campañas activas/pausadas con fecha de fin vencida' => "
                select id_campania as id from campania where estado in ('activa','pausada') and fecha_fin < '{$hoy}'",
            'CU3 · Campañas canceladas o finalizadas con pautas aún programadas' => "
                select distinct c.id_campania as id from campania c join material_publicitario m on m.id_campania = c.id_campania
                join programacion_pauta p on p.id_material = m.id_material
                where c.estado in ('cancelada','finalizada') and p.estado = 'programada'",
            'CU3 · Tarifas activas de servicios dados de baja' => "
                select t.id_tarifa as id from tarifa t join servicio_publicitario s on s.id_servicio = t.id_servicio
                where t.activo and not s.activo",
            'CU3 · Paquetes activos que incluyen tarifas deshabilitadas' => "
                select distinct pa.id_paquete as id from paquete pa join paquete_item i on i.id_paquete = pa.id_paquete
                join tarifa t on t.id_tarifa = i.id_tarifa where pa.activo and not t.activo",
            'CU3 · Paquetes con precio distinto al de sus tarifas' => "
                select pa.id_paquete as id from paquete pa join paquete_item i on i.id_paquete = pa.id_paquete
                join tarifa t on t.id_tarifa = i.id_tarifa group by pa.id_paquete, pa.precio_total, pa.descuento
                having abs(pa.precio_total - round(sum(t.monto * i.cantidad) * (1 - pa.descuento / 100), 2)) > 0.01",
            'Pagos · Pautas programadas o publicadas de proformas sin pagar' => "
                select distinct p.id_programacion as id from programacion_pauta p
                join material_publicitario m on m.id_material = p.id_material join campania c on c.id_campania = m.id_campania
                where p.estado in ('programada','publicada')
                and not exists (select 1 from transaccion_pago t where t.id_proforma = c.id_proforma and t.id_cuota is null and t.estado = 'aprobada')
                and not exists (select 1 from plan_pago pl join cuota q on q.id_plan = pl.id_plan
                                where pl.id_proforma = c.id_proforma and pl.estado in ('activo','completado') and q.estado = 'pagada')",
            'Pagos · Planes activos con todas sus cuotas pagadas' => "
                select pl.id_plan as id from plan_pago pl where pl.estado = 'activo'
                and not exists (select 1 from cuota q where q.id_plan = pl.id_plan and q.estado <> 'pagada')",
            'CU4 · Material aprobado sin ninguna revisión registrada' => "
                select m.id_material as id from material_publicitario m where m.estado in ('aprobado','rechazado','correccion')
                and not exists (select 1 from revision_material r where r.id_material = m.id_material)",
            'CU7 · Métricas de pautas que no están publicadas' => "
                select mp.id_metrica as id from metrica_publicacion mp join programacion_pauta p on p.id_programacion = mp.id_programacion
                where p.estado <> 'publicada'",
            'CU1 · Representantes de empresa cuyo usuario no tiene rol Cliente' => "
                select r.id_usuario as id from representante r join usuario u on u.id_usuario = r.id_usuario
                join rol ro on ro.id_rol = u.id_rol where ro.nombre <> 'Cliente'",
            'CU1 · Usuarios activos con rol o unidad dados de baja' => "
                select u.id_usuario as id from usuario u join rol r on r.id_rol = u.id_rol
                left join unidad_universitaria un on un.id_unidad = u.id_unidad
                where u.activo and (not r.activo or (un.id_unidad is not null and not un.activo))",
            'CU1 · Solicitudes aprobadas sin usuario creado' => "
                select id_solicitud as id from solicitud_cuenta where estado = 'aprobada' and id_usuario is null",
        ];

        $problemas = 0;
        foreach ($revisiones as $titulo => $sql) {
            try {
                $ids = array_map(fn ($r) => $r->id, DB::select($sql));
            } catch (\Throwable $e) {
                $this->warn("? {$titulo}: no se pudo revisar ({$e->getMessage()})");

                continue;
            }
            if ($ids) {
                $problemas++;
                $this->line("<fg=red>✗</> {$titulo}: ".count($ids).' → IDs '.implode(', ', array_slice($ids, 0, 15)).(count($ids) > 15 ? '…' : ''));
            } else {
                $this->line("<fg=green>✓</> {$titulo}");
            }
        }
        $this->newLine();
        $problemas ? $this->warn("{$problemas} revisión(es) con datos a corregir.") : $this->info('Todo coherente.');

        return self::SUCCESS;
    }
}
