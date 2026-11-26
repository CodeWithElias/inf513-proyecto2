<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notificaciones en la campana del encabezado: desactiva la opción del menú lateral.
 * Ejecuta database/sql/06_notificaciones_encabezado.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/06_notificaciones_encabezado.sql')));
    }

    public function down(): void
    {
        DB::table('menu_item')->where('ruta', '/notificaciones')->update(['activo' => true]);
    }
};
