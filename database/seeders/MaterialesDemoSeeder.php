<?php

namespace Database\Seeders;

use App\Models\MaterialPublicitario;
use Illuminate\Database\Seeder;

/**
 * Asocia los materiales de ejemplo (sql/datos.sql del Proyecto 1) a los archivos de
 * database/materiales_demo, para poder visualizarlos desde la web.
 *
 * Idempotente y conservador: solo toca materiales cuyo enlace está vacío o es uno de los
 * enlaces ficticios de Google Drive del Proyecto 1, y solo si existe el archivo de ejemplo
 * con el mismo nombre. Nunca pisa un archivo subido desde la web.
 *
 *   php artisan db:seed --class=MaterialesDemoSeeder
 */
class MaterialesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $carpeta = database_path('materiales_demo');
        $actualizados = 0;

        MaterialPublicitario::query()
            ->where(fn ($q) => $q->whereNull('url_archivo')
                ->orWhere('url_archivo', '')
                ->orWhere('url_archivo', 'like', 'https://drive.google.com/file/d/%'))
            ->each(function (MaterialPublicitario $m) use ($carpeta, &$actualizados) {
                $nombre = basename((string) $m->nombre_archivo);
                $ruta = "{$carpeta}/{$nombre}";
                if ($nombre === '' || ! is_file($ruta)) {
                    return;
                }
                MaterialPublicitario::whereKey($m->getKey())->update([
                    'url_archivo' => "demo/{$nombre}",
                    'tamano_bytes' => filesize($ruta),
                ]);
                $actualizados++;
            });

        $this->command?->info("Materiales asociados a su archivo de ejemplo: {$actualizados}");
    }
}
