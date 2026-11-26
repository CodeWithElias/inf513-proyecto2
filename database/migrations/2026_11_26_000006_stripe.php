<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pasarela Stripe: tabla stripe_cliente y columnas de ids de Stripe en metodo_pago y transaccion_pago.
 * Ejecuta database/sql/09_stripe.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/09_stripe.sql')));
    }

    public function down(): void
    {
        // Se conservan los ids de Stripe ya registrados (trazabilidad de los cobros).
    }
};
