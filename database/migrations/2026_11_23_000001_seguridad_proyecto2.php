<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea las tablas de seguridad del Proyecto 2 (RBAC, menú, bitácora,
 * contadores, tokens de Sanctum) ejecutando los scripts de database/sql.
 * Los scripts son idempotentes: si las tablas ya existen no pasa nada.
 * Las 27 tablas del Proyecto 1 ya existen en la BD y NO se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/01_seguridad.sql')));
        DB::unprepared(file_get_contents(database_path('sql/02_seguridad_datos.sql')));
    }

    public function down(): void
    {
        // No se eliminan datos de auditoría en un rollback.
    }
};
