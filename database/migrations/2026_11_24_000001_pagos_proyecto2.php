<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea las tablas del módulo de Pagos (metodo_pago, plan_pago, cuota,
 * transaccion_pago) y el trigger trg_cuota_pagada ejecutando database/sql/03_pagos.sql.
 * Idempotente: no modifica ninguna tabla del Proyecto 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/03_pagos.sql')));
    }

    public function down(): void
    {
        // No se eliminan transacciones ni historial de pagos en un rollback.
    }
};

