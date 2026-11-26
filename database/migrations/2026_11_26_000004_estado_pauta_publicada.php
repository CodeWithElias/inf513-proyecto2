<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifica el estado de pauta emitida con el del Proyecto 1 ('publicada').
 * Ejecuta database/sql/07_estado_pauta_publicada.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/07_estado_pauta_publicada.sql')));
    }

    public function down(): void
    {
        // Sin vuelta atrás: 'publicada' es el valor correcto para ambos sistemas.
    }
};
