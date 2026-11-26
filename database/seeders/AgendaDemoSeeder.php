<?php

namespace Database\Seeders;

use App\Models\CanalDifusion;
use App\Models\EspacioPublicitario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Agenda de ejemplo de los canales para los próximos DIAS días, para poder contratar:
 *   - Televisión: tres bloques de spots al día (mediodía, tarde y estelar), de 2 minutos.
 *   - Portal web: un banner por día (08:00 a 23:59).
 *   - Redes sociales: una publicación por día en cada red (franja de 30 minutos).
 * Idempotente: no crea un espacio si en ese canal y día ya hay otro que se superpone.
 *   php artisan db:seed --class=AgendaDemoSeeder
 */
class AgendaDemoSeeder extends Seeder
{
    private const DIAS = 60;

    private const HORARIOS = [
        'television' => [['13:00', '13:02'], ['19:30', '19:32'], ['21:30', '21:32']],
        'web' => [['08:00', '23:59']],
        'red_social' => [['17:00', '17:30']],
        'radio' => [['07:30', '07:31'], ['12:30', '12:31']],
    ];

    public function run(): void
    {
        $creados = 0;
        foreach (CanalDifusion::where('activo', true)->get() as $canal) {
            foreach (self::HORARIOS[$canal->tipo] ?? [] as [$inicio, $fin]) {
                for ($i = 1; $i <= self::DIAS; $i++) {
                    $fecha = now()->addDays($i)->toDateString();
                    $choca = EspacioPublicitario::where('id_canal', $canal->id_canal)->where('fecha', $fecha)
                        ->where('hora_inicio', '<', $fin)->where('hora_fin', '>', $inicio)->exists();
                    if (! $choca) {
                        DB::table('espacio_publicitario')->insert([
                            'id_canal' => $canal->id_canal, 'fecha' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => $fin, 'estado' => 'disponible',
                        ]);
                        $creados++;
                    }
                }
            }
        }
        $this->command?->info("Espacios de agenda creados: {$creados}");
    }
}
