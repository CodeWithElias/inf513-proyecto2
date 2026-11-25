<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Los datos iniciales del dominio ya están en la BD (sql/datos.sql del Proyecto 1).
 * Los de seguridad (privilegios, roles y menú) los carga la migración
 * 2026_11_23_000001_seguridad_proyecto2 desde database/sql/02_seguridad_datos.sql.
 * Persona B y C pueden registrar aquí sus seeders (ej. PagosSeeder).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PagosSeeder::class]);
    }
}
