<?php

namespace App\Support;

use App\Exceptions\NegocioException;
use App\Models\MaterialPublicitario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Archivos del material publicitario (CU4). La BD guarda solo la ruta en material_publicitario.url_archivo:
 *   - "materiales/<id>/v<version>-<nombre>.<ext>" → subido desde la web, en storage/app/private (disco "local")
 *   - "demo/<nombre>.<ext>"                       → material de ejemplo, en database/materiales_demo (viaja con el despliegue)
 *   - "https://…"                                 → enlace externo (como en el Proyecto 1)
 * Los archivos internos se ven por GET /api/v1/materiales/{id}/archivo con enlace firmado y temporal.
 */
final class ArchivoMaterial
{
    public const MAX_KB = 20480; // 20 MB

    /** Tipo de material → categorías MIME aceptadas (se revisa el contenido real del archivo). */
    private const MIME = [
        'video' => ['video/'],
        'audio' => ['audio/'],
        'imagen' => ['image/'],
        'banner' => ['image/'],
        'texto' => ['text/plain', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];

    public static function esInterno(?string $url): bool
    {
        return $url !== null && (str_starts_with($url, 'materiales/') || str_starts_with($url, 'demo/'));
    }

    /** Ruta absoluta en disco de un archivo interno, o null (externo, inexistente o ruta sospechosa). */
    public static function rutaAbsoluta(?string $url): ?string
    {
        if (! self::esInterno($url) || str_contains($url, '..')) {
            return null;
        }
        $ruta = str_starts_with($url, 'demo/')
            ? database_path('materiales_demo/'.basename($url))
            : Storage::disk('local')->path($url);

        return is_file($ruta) ? $ruta : null;
    }

    /** Enlace para ver el material: firmado (2 h) si es interno; el enlace tal cual si es externo. */
    public static function urlVista(MaterialPublicitario $m): ?string
    {
        if (self::rutaAbsoluta($m->url_archivo)) {
            return URL::temporarySignedRoute('materiales.archivo', now()->addHours(2), [
                'material' => $m->id_material,
                'v' => $m->version, // cambia con cada versión (evita la caché del navegador)
            ]);
        }

        return preg_match('#^https?://#i', (string) $m->url_archivo) ? $m->url_archivo : null;
    }

    /** Valida el archivo según el tipo de material (extensión y contenido real). Devuelve [extensión, mime]. */
    public static function validar(UploadedFile $archivo, string $tipo): array
    {
        if (! $archivo->isValid()) {
            throw new NegocioException('El archivo no se pudo subir. Inténtelo de nuevo (máximo 20 MB).');
        }
        $ext = strtolower($archivo->getClientOriginalExtension());
        $permitidas = MaterialPublicitario::FORMATOS_VALIDOS[$tipo] ?? [];
        if (! in_array($ext, $permitidas, true)) {
            throw new NegocioException("Un archivo «.{$ext}» no corresponde a un material de tipo {$tipo}. Formatos permitidos: ".implode(', ', $permitidas).'.');
        }
        $mime = (string) $archivo->getMimeType(); // detectado por el contenido, no por el nombre
        $ok = collect(self::MIME[$tipo] ?? [])->contains(fn ($p) => str_ends_with($p, '/') ? str_starts_with($mime, $p) : $mime === $p);
        if (! $ok) {
            throw new NegocioException("El contenido del archivo ({$mime}) no corresponde a un material de tipo {$tipo}.");
        }

        return [$ext, $mime];
    }

    /** Guarda el archivo en storage y devuelve la ruta para url_archivo. */
    public static function guardar(UploadedFile $archivo, int $idMaterial, int $version, string $ext): string
    {
        // Si el servidor no deja escribir en laravel/storage, entorno.php usa /tmp: ahí los archivos se perderían.
        if (! empty($_ENV['LARAVEL_STORAGE_PATH']) || ! is_writable(storage_path('app'))) {
            throw new NegocioException('El servidor no permite guardar archivos en este momento. Registre el material con un enlace (URL) o avise al administrador.', 503);
        }
        $base = Str::limit(Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)), 80, '') ?: 'archivo';
        $ruta = $archivo->storeAs("materiales/{$idMaterial}", "v{$version}-{$base}.{$ext}", 'local');
        if (! $ruta) {
            throw new NegocioException('No se pudo guardar el archivo en el servidor.', 500);
        }

        return $ruta;
    }

    /** Borra un archivo subido (no toca los de ejemplo ni los enlaces externos). */
    public static function borrar(?string $url): void
    {
        if ($url && str_starts_with($url, 'materiales/') && ! str_contains($url, '..')) {
            Storage::disk('local')->delete($url);
        }
    }
}
