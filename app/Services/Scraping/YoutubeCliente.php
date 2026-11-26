<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Fuente de datos externa: búsqueda pública de YouTube (portado de grupo13.datos.DYoutube).
 * Descarga la página de resultados, lee el JSON "ytInitialData" y pide las páginas siguientes a la
 * API interna (youtubei/v1/search) con el token de continuación. Solo obtiene datos; no decide nada.
 *
 * Fila de resultado: ['tipo' => 'canal'|'video', 'channel_id', 'handle' (/@... o null), 'nombre', 'texto', 'url_video']
 *   - canal: texto = descripción del canal
 *   - video: texto = título del video + fragmento de su descripción
 */
class YoutubeCliente
{
    private const BASE = 'https://www.youtube.com';
    private const FILTRO_CANALES = 'EgIQAg%3D%3D';
    private const FILTRO_VIDEOS = 'EgIQAQ%3D%3D';
    private const AGENTE = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /** Canales y videos que YouTube devuelve para el término. Lanza RuntimeException con mensaje en español si falla. */
    public function buscar(string $termino, int $paginas): array
    {
        $resultados = [];
        $this->buscarCon($termino, self::FILTRO_CANALES, $paginas, $resultados);
        $this->buscarCon($termino, self::FILTRO_VIDEOS, $paginas, $resultados);

        return $resultados;
    }

    private function buscarCon(string $termino, string $filtro, int $paginas, array &$resultados): void
    {
        $html = $this->get(self::BASE.'/results?search_query='.urlencode($termino).'&sp='.$filtro);
        if (! preg_match('/var ytInitialData = (\{.*?\});<\/script>/s', $html, $m)) {
            throw new RuntimeException('YouTube no devolvió resultados reconocibles (posible bloqueo o cambio de formato).');
        }
        $version = preg_match('/"INNERTUBE_CLIENT_VERSION":"([^"]+)"/', $html, $v) ? $v[1] : '2.20240101.00.00';
        $datos = json_decode($m[1], true);
        if (! is_array($datos)) {
            throw new RuntimeException('No se pudo leer la respuesta de YouTube.');
        }

        for ($pagina = 1; ; $pagina++) {
            $this->extraer($datos, $resultados);
            $token = $this->continuacion($datos);
            if ($token === null || $pagina >= $paginas) {
                return;
            }
            $datos = $this->post(self::BASE.'/youtubei/v1/search?prettyPrint=false', [
                'context' => ['client' => ['clientName' => 'WEB', 'clientVersion' => $version, 'hl' => 'es', 'gl' => 'BO']],
                'continuation' => $token,
            ]);
        }
    }

    private function extraer(array $datos, array &$resultados): void
    {
        $canales = [];
        self::buscarClave($datos, 'channelRenderer', $canales);
        foreach ($canales as $c) {
            $resultados[] = [
                'tipo' => 'canal',
                'channel_id' => $c['channelId'] ?? null,
                'handle' => self::ruta($c, ['navigationEndpoint', 'browseEndpoint', 'canonicalBaseUrl']),
                'nombre' => self::texto($c['title'] ?? null),
                'texto' => self::texto($c['descriptionSnippet'] ?? null),
                'url_video' => null,
            ];
        }

        $videos = [];
        self::buscarClave($datos, 'videoRenderer', $videos);
        foreach ($videos as $vr) {
            $dueno = self::ruta($vr, ['ownerText', 'runs', 0]);
            $fragmento = self::texto(self::ruta($vr, ['detailedMetadataSnippets', 0, 'snippetText']));
            $resultados[] = [
                'tipo' => 'video',
                'channel_id' => self::ruta($dueno, ['navigationEndpoint', 'browseEndpoint', 'browseId']),
                'handle' => self::ruta($dueno, ['navigationEndpoint', 'browseEndpoint', 'canonicalBaseUrl']),
                'nombre' => self::ruta($dueno, ['text']),
                'texto' => self::texto($vr['title'] ?? null).($fragmento ? ' — '.$fragmento : ''),
                'url_video' => self::BASE.'/watch?v='.($vr['videoId'] ?? ''),
            ];
        }
    }

    private function continuacion(array $datos): ?string
    {
        $comandos = [];
        self::buscarClave($datos, 'continuationCommand', $comandos);

        return $comandos[0]['token'] ?? null;
    }

    // ------------------------------------------------------------------ HTTP

    private function cliente()
    {
        $peticion = Http::withHeaders(['User-Agent' => self::AGENTE, 'Accept-Language' => 'es-419,es;q=0.9'])
            ->connectTimeout(20)
            ->timeout(30);

        // La verificación del certificado SIEMPRE está activa; solo se indica dónde están los certificados raíz.
        $ca = config('monitoreo.ca_bundle');

        return $ca ? $peticion->withOptions(['verify' => $ca]) : $peticion;
    }

    private function get(string $url): string
    {
        try {
            $r = $this->cliente()->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo conectar con YouTube: '.$e->getMessage());
        }
        if ($r->status() !== 200) {
            throw new RuntimeException("YouTube respondió HTTP {$r->status()}.");
        }

        return $r->body();
    }

    private function post(string $url, array $cuerpo): array
    {
        try {
            $r = $this->cliente()->asJson()->post($url, $cuerpo);
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo conectar con YouTube: '.$e->getMessage());
        }
        if ($r->status() !== 200) {
            throw new RuntimeException("YouTube respondió HTTP {$r->status()}.");
        }

        return $r->json() ?? [];
    }

    // ------------------------------------------------------------------ JSON

    /** Todos los objetos con la clave $clave, a cualquier profundidad. */
    private static function buscarClave(mixed $nodo, string $clave, array &$salida): void
    {
        if (! is_array($nodo)) {
            return;
        }
        foreach ($nodo as $k => $v) {
            if ($k === $clave && is_array($v)) {
                $salida[] = $v;
            } else {
                self::buscarClave($v, $clave, $salida);
            }
        }
    }

    private static function ruta(mixed $nodo, array $camino): mixed
    {
        foreach ($camino as $paso) {
            if (! is_array($nodo) || ! array_key_exists($paso, $nodo)) {
                return null;
            }
            $nodo = $nodo[$paso];
        }

        return $nodo;
    }

    /** Texto de un nodo {"simpleText": "..."} o {"runs": [{"text": "..."}, ...]}. */
    private static function texto(mixed $nodo): ?string
    {
        if (! is_array($nodo)) {
            return is_string($nodo) ? $nodo : null;
        }
        if (isset($nodo['simpleText'])) {
            return $nodo['simpleText'];
        }
        if (isset($nodo['runs']) && is_array($nodo['runs'])) {
            return implode('', array_map(fn ($r) => $r['text'] ?? '', $nodo['runs']));
        }

        return null;
    }
}
