<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\EjecucionScraping;
use App\Models\PaginaNoAutorizada;
use App\Models\TerminoMonitoreo;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * CU6 - Monitoreo de páginas no autorizadas (portado de NTermino y NPagina).
 * En la web el registro de páginas es manual; las detecciones automáticas
 * (scraping de YouTube del Proyecto 1) se muestran en Ejecuciones.
 */
class MonitoreoService
{
    // ----------------------------- Términos -----------------------------

    public function listarTerminos(array $f, int $porPagina): LengthAwarePaginator
    {
        return TerminoMonitoreo::query()
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('termino', 'ilike', "%{$v}%"))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('id_termino')
            ->paginate($porPagina);
    }

    public function crearTermino(array $d): TerminoMonitoreo
    {
        $t = trim($d['termino']);
        if (TerminoMonitoreo::whereRaw('lower(termino) = lower(?)', [$t])->exists()) {
            throw new NegocioException("El término «{$t}» ya está registrado.");
        }

        return TerminoMonitoreo::create(['termino' => $t, 'tipo' => $d['tipo'] ?? null])->refresh();
    }

    public function cambiarEstadoTermino(TerminoMonitoreo $t, bool $activo): TerminoMonitoreo
    {
        if ($t->activo === $activo) {
            throw new NegocioException($activo ? "El término «{$t->termino}» ya está activo." : "El término «{$t->termino}» ya está desactivado.");
        }
        $t->update(['activo' => $activo]);

        return $t;
    }

    // ----------------------------- Páginas ------------------------------

    public function listarPaginas(array $f, int $porPagina): LengthAwarePaginator
    {
        return PaginaNoAutorizada::query()
            ->with(['plataforma:id_plataforma,nombre', 'revisor:id_usuario,nombre_completo'])
            ->withCount('detecciones')
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('url', 'ilike', "%{$v}%")->orWhere('nombre_pagina', 'ilike', "%{$v}%")))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['id_plataforma'] ?? null, fn ($q, $v) => $q->where('id_plataforma', $v))
            ->orderByDesc('id_pagina')
            ->paginate($porPagina);
    }

    public function obtenerPagina(PaginaNoAutorizada $p): PaginaNoAutorizada
    {
        return $p->load([
            'plataforma:id_plataforma,nombre',
            'revisor:id_usuario,nombre_completo',
            'detecciones' => fn ($q) => $q->orderByDesc('fecha_deteccion'),
            'detecciones.termino:id_termino,termino',
        ]);
    }

    public function registrarPagina(array $d): PaginaNoAutorizada
    {
        if (PaginaNoAutorizada::whereRaw('lower(url) = lower(?)', [$d['url']])->exists()) {
            throw new NegocioException('Esa URL ya está registrada como página no autorizada.');
        }

        return PaginaNoAutorizada::create([
            'url' => $d['url'],
            'nombre_pagina' => $d['nombre_pagina'] ?? null,
            'id_plataforma' => $d['id_plataforma'],
            'estado' => PaginaNoAutorizada::DETECTADA,
        ])->load('plataforma:id_plataforma,nombre');
    }

    /** Cambia el estado siguiendo detectada → confirmada → denunciada → eliminada. */
    public function revisarPagina(PaginaNoAutorizada $p, string $nuevo, Usuario $actor): PaginaNoAutorizada
    {
        $posibles = PaginaNoAutorizada::TRANSICIONES[$p->estado] ?? [];
        if (! in_array($nuevo, $posibles, true)) {
            throw new NegocioException("La página está «{$p->estado}» y no puede pasar a «{$nuevo}».".
                ($posibles ? ' Estado siguiente posible: '.implode(', ', $posibles).'.' : ' Ya no admite cambios.'));
        }
        if ($actor->idEmpresa() !== null) {
            throw new NegocioException('La revisión de páginas la hace el personal de Canal 11, no los clientes.', 403);
        }
        $p->update(['estado' => $nuevo, 'id_usuario' => $actor->id_usuario, 'fecha_revision' => now()]);

        return $this->obtenerPagina($p);
    }

    // ---------------------------- Ejecuciones ---------------------------

    public function listarEjecuciones(array $f, int $porPagina): LengthAwarePaginator
    {
        return EjecucionScraping::query()
            ->with('plataforma:id_plataforma,nombre')
            ->withCount('detecciones')
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->orderByDesc('id_ejecucion')
            ->paginate($porPagina);
    }
}
