<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contratación guiada de proformas (tablas contratacion_servicio y contratacion_espacio).
 * Ejecuta database/sql/08_contratacion.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/08_contratacion.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS contratacion_espacio; DROP TABLE IF EXISTS contratacion_servicio;');
    }
};
