<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Solicitudes de cuenta de visitantes (tabla solicitud_cuenta, privilegios
 * solicitudes.ver/editar y opción de menú). Ejecuta database/sql/05_solicitudes_cuenta.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/05_solicitudes_cuenta.sql')));
    }

    public function down(): void
    {
        // No se borran solicitudes en un rollback.
    }
};
