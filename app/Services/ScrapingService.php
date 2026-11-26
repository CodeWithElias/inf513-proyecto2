<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\EjecucionScraping;
use App\Models\PaginaNoAutorizada;
use App\Models\Plataforma;
use App\Models\TerminoMonitoreo;
use App\Services\Scraping\YoutubeCliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * CU6 / RF12 - Ejecución manual del monitoreo (portado de grupo13.negocio.NScraping).
 *  1. Por cada término activo (o uno elegido) se buscan canales y videos en YouTube.
 *  2. Solo cuentan los resultados que contienen el término (sin distinguir mayúsculas ni tildes).
 *  3. Cada canal se compara con las redes oficiales de la plataforma (CU2): las oficiales se ignoran.
 *  4. Canal no oficial y nuevo → página "detectada" + detección con evidencia + aviso al rol de alertas.
 *     Ya registrado → solo se agrega la detección. "eliminada" → se reabre como "detectada" y se avisa.
 *  5. Todo se guarda en una transacción y la ejecución queda registrada (completada o error).
 *  6. No se permiten dos ejecuciones simultáneas de la misma plataforma.
 */
class ScrapingService
{
    private const BASE_YOUTUBE = 'https://www.youtube.com/';
    private const MAX_EVIDENCIAS = 3;
    private const MAX_TEXTO = 300;
    private const MEDIO = 'sistema';

    public function __construct(private readonly YoutubeCliente $youtube)
    {
    }

    public function ejecutar(int $idPlataforma, ?int $idTermino): array
    {
        $plataforma = Plataforma::find($idPlataforma);
        if (! $plataforma) {
            throw new NegocioException('La plataforma seleccionada no existe.');
        }
        if (! in_array(mb_strtolower($plataforma->nombre), config('monitoreo.soportadas'), true)) {
            throw new NegocioException("El monitoreo automático todavía no está disponible para {$plataforma->nombre}; por ahora solo para YouTube (Facebook y TikTok bloquean las búsquedas automáticas).");
        }
        $terminos = $this->terminos($idTermino);
        $this->verificarQueNoHayaOtra($plataforma);

        // Varias búsquedas en YouTube: dar tiempo suficiente a PHP.
        @set_time_limit(240);

        $ejecucion = EjecucionScraping::create(['id_plataforma' => $plataforma->id_plataforma, 'estado' => 'en_proceso']);
        try {
            return $this->procesar($ejecucion, $plataforma, $terminos);
        } catch (Throwable $e) {
            EjecucionScraping::whereKey($ejecucion->id_ejecucion)->update(['estado' => 'error', 'fecha_fin' => DB::raw('CURRENT_TIMESTAMP'), 'mensaje_error' => Str::limit('Falla al procesar: '.$e->getMessage(), 900)]);
            throw $e;
        }
    }

    /** Términos a buscar: todos los activos o solo el indicado (debe estar activo). */
    private function terminos(?int $idTermino)
    {
        if ($idTermino === null) {
            $activos = TerminoMonitoreo::where('activo', true)->orderBy('id_termino')->get(['id_termino', 'termino']);
            if ($activos->isEmpty()) {
                throw new NegocioException('No hay términos de monitoreo activos. Registre al menos uno antes de ejecutar el monitoreo.');
            }

            return $activos;
        }
        $t = TerminoMonitoreo::find($idTermino);
        if (! $t) {
            throw new NegocioException('El término seleccionado no existe.');
        }
        if (! $t->activo) {
            throw new NegocioException("El término «{$t->termino}» está desactivado. Reactívelo antes de buscarlo.");
        }

        return collect([$t]);
    }

    /** Las fechas se comparan en la BD (CURRENT_TIMESTAMP), igual que en el Proyecto 1, para no depender de zonas horarias. */
    private function verificarQueNoHayaOtra(Plataforma $p): void
    {
        $limite = (int) config('monitoreo.minutos_en_curso');
        $enCurso = EjecucionScraping::where('id_plataforma', $p->id_plataforma)->where('estado', 'en_proceso');

        $reciente = (clone $enCurso)->whereRaw("fecha_inicio > CURRENT_TIMESTAMP - (? || ' minutes')::interval", [$limite])->first();
        if ($reciente) {
            throw new NegocioException("Ya hay un monitoreo de {$p->nombre} en curso (ejecución {$reciente->id_ejecucion}). Espere a que termine.");
        }
        $enCurso->update(['estado' => 'error', 'fecha_fin' => DB::raw('CURRENT_TIMESTAMP'), 'mensaje_error' => "Interrumpida: no terminó en {$limite} minutos."]);
    }

    private function procesar(EjecucionScraping $ejecucion, Plataforma $plataforma, $terminos): array
    {
        // 1. Búsqueda en YouTube (fuente externa)
        $errores = [];
        $cuentas = [];
        $paginas = max(1, (int) config('monitoreo.paginas'));
        foreach ($terminos as $t) {
            try {
                foreach ($this->youtube->buscar($t->termino, $paginas) as $r) {
                    $this->agregarCoincidencia($cuentas, $r, $t);
                }
            } catch (RuntimeException $e) {
                $errores[] = "Término «{$t->termino}»: ".$e->getMessage();
            }
        }

        // 2. Comparación con las redes oficiales (CU2) y con las páginas ya registradas
        $oficiales = DB::table('red_social')->where('id_plataforma', $plataforma->id_plataforma)->pluck('url')
            ->map(fn ($u) => self::normalizarUrl($u))->flip();
        $registradas = [];
        foreach (PaginaNoAutorizada::where('id_plataforma', $plataforma->id_plataforma)->get(['id_pagina', 'url', 'estado']) as $p) {
            $registradas[self::normalizarUrl($p->url)] = $p;
        }
        $avisados = DB::table('usuario as u')->join('rol as r', 'r.id_rol', '=', 'u.id_rol')
            ->whereRaw('lower(r.nombre) = lower(?)', [config('monitoreo.alertas_rol')])
            ->where('u.activo', true)->where('r.activo', true)->pluck('u.id_usuario');

        $resumen = ['oficiales' => 0, 'nuevas' => 0, 'ya_registradas' => 0, 'paginas_nuevas' => []];

        // 3. Todo en una sola transacción
        DB::transaction(function () use ($cuentas, $oficiales, $registradas, $avisados, $plataforma, $ejecucion, &$resumen) {
            foreach ($cuentas as $c) {
                $urls = self::urls($c);
                if (collect($urls)->contains(fn ($u) => $oficiales->has(self::normalizarUrl($u)))) {
                    $resumen['oficiales']++;
                    continue;
                }
                $existente = collect($urls)->map(fn ($u) => $registradas[self::normalizarUrl($u)] ?? null)->filter()->first();
                $reabre = $existente && $existente->estado === PaginaNoAutorizada::ELIMINADA;

                if (! $existente) {
                    $pagina = PaginaNoAutorizada::create([
                        'url' => self::url($c), 'nombre_pagina' => Str::limit($c['nombre'], 195, ''),
                        'estado' => PaginaNoAutorizada::DETECTADA, 'id_plataforma' => $plataforma->id_plataforma,
                    ]);
                } else {
                    $pagina = $existente;
                    if ($reabre) {
                        $pagina->update(['estado' => PaginaNoAutorizada::DETECTADA]);
                    }
                }

                $primera = null;
                foreach ($c['por_termino'] as $termino => $evidencias) {
                    $id = DB::table('deteccion_pagina')->insertGetId([
                        'id_pagina' => $pagina->id_pagina,
                        'id_termino' => $c['id_terminos'][$termino],
                        'id_ejecucion' => $ejecucion->id_ejecucion,
                        'evidencia' => implode("\n", $evidencias),
                    ], 'id_deteccion');
                    $primera ??= $id;
                }

                if (! $existente || $reabre) {
                    $resumen['nuevas']++;
                    $resumen['paginas_nuevas'][] = ['nombre' => $c['nombre'], 'url' => self::url($c)];
                    $titulo = ($reabre ? 'Reapareció una página no autorizada en ' : 'Página no autorizada detectada en ').$plataforma->nombre;
                    $mensaje = "La cuenta «{$c['nombre']}» (".self::url($c).') usa los términos «'.implode('», «', array_keys($c['por_termino']))
                        .'» y no es una red oficial registrada. Evidencia: '.$c['evidencias'][0];
                    foreach ($avisados as $idUsuario) {
                        DB::table('notificacion')->insert([
                            'medio' => self::MEDIO, 'titulo' => Str::limit($titulo, 195, ''), 'mensaje' => $mensaje,
                            'id_usuario' => $idUsuario, 'id_deteccion' => $primera,
                        ]);
                    }
                } else {
                    $resumen['ya_registradas']++;
                }
            }
        });

        // 4. Cierre de la ejecución
        $estado = count($errores) === count($terminos) ? 'error' : 'completada';
        $detalleErrores = $errores ? Str::limit(implode(' | ', $errores), 900) : null;
        EjecucionScraping::whereKey($ejecucion->id_ejecucion)->update(['estado' => $estado, 'fecha_fin' => DB::raw('CURRENT_TIMESTAMP'), 'mensaje_error' => $detalleErrores]);

        return [
            'id_ejecucion' => $ejecucion->id_ejecucion,
            'plataforma' => $plataforma->nombre,
            'estado' => $estado,
            'terminos' => $terminos->pluck('termino')->all(),
            'cuentas_analizadas' => count($cuentas),
            'oficiales' => $resumen['oficiales'],
            'nuevas' => $resumen['nuevas'],
            'ya_registradas' => $resumen['ya_registradas'],
            'paginas_nuevas' => $resumen['paginas_nuevas'],
            'errores' => $detalleErrores,
        ];
    }

    /** Agrega el resultado si contiene el término (sin distinguir mayúsculas ni tildes). */
    private function agregarCoincidencia(array &$cuentas, array $r, TerminoMonitoreo $t): void
    {
        if (empty($r['channel_id'])) {
            return;
        }
        $nombre = (string) ($r['nombre'] ?? '');
        $texto = (string) ($r['texto'] ?? '');
        if (! str_contains(self::normalizar("{$nombre} {$texto}"), self::normalizar($t->termino))) {
            return;
        }

        $c = &$cuentas[$r['channel_id']];
        $c ??= ['channel_id' => $r['channel_id'], 'handle' => null, 'nombre' => $nombre, 'id_terminos' => [], 'por_termino' => [], 'evidencias' => []];
        if (! empty($r['handle']) && str_starts_with($r['handle'], '/@')) {
            $c['handle'] = $r['handle'];
        }
        $c['nombre'] = $nombre ?: $c['nombre'];
        $c['id_terminos'][$t->termino] = $t->id_termino;
        $c['por_termino'][$t->termino] ??= [];

        if (count($c['por_termino'][$t->termino]) < self::MAX_EVIDENCIAS) {
            $evidencia = $r['tipo'] === 'canal'
                ? "Canal «{$nombre}»".($texto !== '' ? ': '.self::recortar($texto) : '')
                : 'Video «'.self::recortar($texto)."» ({$r['url_video']})";
            $c['por_termino'][$t->termino][] = $evidencia;
            $c['evidencias'][] = $evidencia;
        }
    }

    private static function url(array $c): string
    {
        // handle = "/@canal" → https://www.youtube.com/@canal
        return $c['handle'] ? self::BASE_YOUTUBE.substr($c['handle'], 1) : self::BASE_YOUTUBE.'channel/'.$c['channel_id'];
    }

    private static function urls(array $c): array
    {
        return array_values(array_filter([
            $c['handle'] ? self::BASE_YOUTUBE.substr($c['handle'], 1) : null,
            self::BASE_YOUTUBE.'channel/'.$c['channel_id'],
        ]));
    }

    /** youtube.com/@handle, youtube.com/channel/ID, /c/nombre o /user/nombre (sin protocolo, www, parámetros ni barra final). */
    public static function normalizarUrl(string $url): string
    {
        $u = mb_strtolower(trim($url));
        $u = preg_replace('#^https?://#', '', $u);
        $u = preg_replace('#^(www\.|m\.)#', '', $u);
        $u = preg_replace('/[?#].*$/', '', $u);
        $partes = explode('/', $u);
        if (count($partes) >= 3 && in_array($partes[1], ['channel', 'c', 'user'], true)) {
            return "{$partes[0]}/{$partes[1]}/{$partes[2]}";
        }

        return count($partes) >= 2 ? "{$partes[0]}/{$partes[1]}" : $u;
    }

    private static function normalizar(string $s): string
    {
        return mb_strtolower(Str::ascii($s));
    }

    private static function recortar(string $s): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $s)), self::MAX_TEXTO);
    }
}
