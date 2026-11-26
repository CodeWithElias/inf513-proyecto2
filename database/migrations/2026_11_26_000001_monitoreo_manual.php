<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Privilegio ejecuciones.crear (ejecutar el monitoreo de YouTube a mano, como ejeScr del
 * Proyecto 1) asignado al Administrador y al Director. Ejecuta database/sql/04_monitoreo_manual.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/04_monitoreo_manual.sql')));
    }

    public function down(): void
    {
        DB::table('privilegio')->where('codigo', 'ejecuciones.crear')->delete();
    }
};
