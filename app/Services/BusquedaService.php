<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\EmpresaAnunciante;
use App\Models\MaterialPublicitario;
use App\Models\PaginaNoAutorizada;
use App\Models\Paquete;
use App\Models\Proforma;
use App\Models\RedSocial;
use App\Models\Representante;
use App\Models\ServicioPublicitario;
use App\Models\UnidadUniversitaria;
use App\Models\Usuario;

/**
 * Búsqueda global predictiva (encabezado). Busca en todos los módulos a los
 * que el rol tiene acceso (privilegio "<recurso>.ver") y aplica "solo lo suyo"
 * (Cliente → su empresa, Encargado → sus redes). Máximo 5 resultados por tipo.
 * Cada resultado trae una ruta con ?q=... para abrir la lista ya filtrada.
 */
class BusquedaService
{
    private const LIMITE = 5;

    private const CATEGORIAS = ['empresas', 'servicios', 'paquetes', 'proformas', 'campanias',
        'usuarios', 'clientes', 'unidades', 'redes', 'materiales', 'paginas'];

    public function buscar(string $termino, Usuario $actor): array
    {
        $q = trim($termino);
        if (mb_strlen($q) < 2) {
            return array_fill_keys(self::CATEGORIAS, []);
        }

        $like = "%{$q}%";

        return $this->comerciales($actor, $like) + $this->operativos($actor, $like);
    }

    /** Ruta de la pantalla con la lista filtrada por el texto del resultado. */
    private function ruta(string $pantalla, ?string $filtro): string
    {
        return $filtro ? $pantalla.'?q='.rawurlencode($filtro) : $pantalla;
    }

    private function operativos(Usuario $actor, string $like): array
    {
        return [
            'usuarios' => $actor->tienePrivilegio('usuarios.ver')
                ? Usuario::query()
                    ->with('rol:id_rol,nombre')
                    ->where(fn ($w) => $w->where('nombre_completo', 'ilike', $like)->orWhere('email', 'ilike', $like))
                    ->orderBy('nombre_completo')
                    ->limit(self::LIMITE)
                    ->get(['id_usuario', 'nombre_completo', 'email', 'id_rol', 'activo'])
                    ->map(fn ($u) => [
                        'id' => $u->id_usuario,
                        'titulo' => $u->nombre_completo,
                        'subtitulo' => "{$u->email} · ".($u->rol?->nombre ?? 'Sin rol').($u->activo ? '' : ' · inactivo'),
                        'ruta' => $this->ruta('/usuarios', $u->email),
                    ])->all()
                : [],

            'clientes' => $actor->tienePrivilegio('representantes.ver')
                ? Representante::query()
                    ->with(['usuario:id_usuario,nombre_completo,email', 'empresa:id_empresa,razon_social'])
                    ->where(fn ($w) => $w
                        ->where('ci', 'ilike', $like)
                        ->orWhereHas('usuario', fn ($u) => $u->where('nombre_completo', 'ilike', $like)->orWhere('email', 'ilike', $like))
                        ->orWhereHas('empresa', fn ($e) => $e->where('razon_social', 'ilike', $like)))
                    ->limit(self::LIMITE)
                    ->get(['id_usuario', 'ci', 'id_empresa'])
                    ->map(fn ($r) => [
                        'id' => $r->id_usuario,
                        'titulo' => $r->usuario?->nombre_completo ?? "CI {$r->ci}",
                        'subtitulo' => "CI {$r->ci} · ".($r->empresa?->razon_social ?? ''),
                        'ruta' => $this->ruta('/representantes', $r->ci),
                    ])->all()
                : [],

            'unidades' => $actor->tienePrivilegio('unidades.ver')
                ? UnidadUniversitaria::query()
                    ->where(fn ($w) => $w->where('nombre', 'ilike', $like)->orWhere('tipo', 'ilike', $like))
                    ->orderBy('nombre')
                    ->limit(self::LIMITE)
                    ->get(['id_unidad', 'nombre', 'tipo', 'activo'])
                    ->map(fn ($u) => [
                        'id' => $u->id_unidad,
                        'titulo' => $u->nombre,
                        'subtitulo' => ($u->tipo ?? 'Unidad universitaria').($u->activo ? '' : ' · inactiva'),
                        'ruta' => $this->ruta('/unidades', $u->nombre),
                    ])->all()
                : [],

            'redes' => $actor->tienePrivilegio('redes.ver')
                ? RedSocial::query()
                    ->with(['canal:id_canal,nombre,activo', 'plataforma:id_plataforma,nombre', 'unidad:id_unidad,nombre'])
                    ->when($actor->esEncargado(), fn ($b) => $b->where(fn ($w) => $w
                        ->where('id_usuario', $actor->id_usuario)
                        ->when($actor->id_unidad, fn ($x) => $x->orWhere('id_unidad', $actor->id_unidad))))
                    ->where(fn ($w) => $w
                        ->where('url', 'ilike', $like)
                        ->orWhereHas('canal', fn ($c) => $c->where('nombre', 'ilike', $like))
                        ->orWhereHas('plataforma', fn ($p) => $p->where('nombre', 'ilike', $like)))
                    ->limit(self::LIMITE)
                    ->get(['id_canal', 'url', 'id_plataforma', 'id_unidad', 'id_usuario'])
                    ->map(fn ($r) => [
                        'id' => $r->id_canal,
                        'titulo' => $r->canal?->nombre ?? $r->url,
                        'subtitulo' => ($r->plataforma?->nombre ?? '').($r->unidad ? " · {$r->unidad->nombre}" : ''),
                        'ruta' => $this->ruta('/redes', $r->canal?->nombre),
                    ])->all()
                : [],

            'materiales' => $actor->tienePrivilegio('materiales.ver')
                ? MaterialPublicitario::query()
                    ->with('campania:id_campania,nombre,id_proforma')
                    ->when($actor->esCliente(), fn ($b) => $b->whereHas('campania.proforma',
                        fn ($p) => $p->where('id_empresa', $actor->idEmpresa() ?? 0)))
                    ->where(fn ($w) => $w
                        ->where('nombre_archivo', 'ilike', $like)
                        ->orWhereHas('campania', fn ($c) => $c->where('nombre', 'ilike', $like)))
                    ->orderByDesc('id_material')
                    ->limit(self::LIMITE)
                    ->get(['id_material', 'nombre_archivo', 'tipo', 'estado', 'version', 'id_campania'])
                    ->map(fn ($m) => [
                        'id' => $m->id_material,
                        'titulo' => $m->nombre_archivo ?? "Material #{$m->id_material}",
                        'subtitulo' => "{$m->tipo} v{$m->version} · {$m->estado}".($m->campania ? " · {$m->campania->nombre}" : ''),
                        'ruta' => $this->ruta('/materiales', $m->nombre_archivo),
                    ])->all()
                : [],

            'paginas' => $actor->tienePrivilegio('paginas.ver')
                ? PaginaNoAutorizada::query()
                    ->with('plataforma:id_plataforma,nombre')
                    ->where(fn ($w) => $w->where('nombre_pagina', 'ilike', $like)->orWhere('url', 'ilike', $like))
                    ->orderByDesc('id_pagina')
                    ->limit(self::LIMITE)
                    ->get(['id_pagina', 'nombre_pagina', 'url', 'estado', 'id_plataforma'])
                    ->map(fn ($p) => [
                        'id' => $p->id_pagina,
                        'titulo' => $p->nombre_pagina ?? $p->url,
                        'subtitulo' => ($p->plataforma?->nombre ?? '')." · {$p->estado}",
                        'ruta' => $this->ruta('/paginas', $p->nombre_pagina ?? $p->url),
                    ])->all()
                : [],
        ];
    }

    private function comerciales(Usuario $actor, string $like): array
    {
        return [
            'empresas' => $actor->tienePrivilegio('empresas.ver')
                ? EmpresaAnunciante::query()
                    ->when($actor->esCliente(), fn ($b) => $b->where('id_empresa', $actor->idEmpresa() ?? 0))
                    ->where(fn ($w) => $w
                        ->where('razon_social', 'ilike', $like)
                        ->orWhere('nit', 'ilike', $like)
                        ->orWhere('nombre_comercial', 'ilike', $like))
                    ->orderBy('razon_social')
                    ->limit(self::LIMITE)
                    ->get(['id_empresa', 'razon_social', 'nit', 'nombre_comercial'])
                    ->map(fn ($e) => [
                        'id' => $e->id_empresa,
                        'titulo' => $e->razon_social,
                        'subtitulo' => "NIT {$e->nit}".($e->nombre_comercial ? " · {$e->nombre_comercial}" : ''),
                        'ruta' => $this->ruta('/empresas', $e->razon_social),
                    ])->all()
                : [],

            'servicios' => $actor->tienePrivilegio('servicios.ver')
                ? ServicioPublicitario::query()
                    ->where(fn ($w) => $w
                        ->where('nombre', 'ilike', $like)
                        ->orWhere('descripcion', 'ilike', $like)
                        ->orWhere('tipo', 'ilike', $like))
                    ->orderBy('nombre')
                    ->limit(self::LIMITE)
                    ->get(['id_servicio', 'nombre', 'tipo'])
                    ->map(fn ($s) => [
                        'id' => $s->id_servicio,
                        'titulo' => $s->nombre,
                        'subtitulo' => $s->tipo ?? 'Servicio publicitario',
                        'ruta' => $this->ruta('/servicios', $s->nombre),
                    ])->all()
                : [],

            'paquetes' => $actor->tienePrivilegio('paquetes.ver')
                ? Paquete::query()
                    ->where(fn ($w) => $w
                        ->where('nombre', 'ilike', $like)
                        ->orWhere('descripcion', 'ilike', $like))
                    ->orderBy('nombre')
                    ->limit(self::LIMITE)
                    ->get(['id_paquete', 'nombre', 'precio_total', 'descuento'])
                    ->map(fn ($p) => [
                        'id' => $p->id_paquete,
                        'titulo' => $p->nombre,
                        'subtitulo' => 'Bs '.number_format((float) $p->precio_total, 2, ',', '.')
                            .($p->descuento > 0 ? " (-{$p->descuento}%)" : ''),
                        'ruta' => $this->ruta('/paquetes', $p->nombre),
                    ])->all()
                : [],

            'proformas' => $actor->tienePrivilegio('proformas.ver')
                ? Proforma::query()
                    ->with(['empresa:id_empresa,razon_social', 'visitante:id_visitante,nombre'])
                    ->when($actor->esCliente(), fn ($b) => $b->where('id_empresa', $actor->idEmpresa() ?? 0))
                    ->where(fn ($w) => $w
                        ->where('nro_proforma', 'ilike', $like)
                        ->orWhereHas('empresa', fn ($e) => $e->where('razon_social', 'ilike', $like))
                        ->orWhereHas('visitante', fn ($v) => $v->where('nombre', 'ilike', $like)))
                    ->orderByDesc('id_proforma')
                    ->limit(self::LIMITE)
                    ->get(['id_proforma', 'nro_proforma', 'total', 'estado', 'id_empresa', 'id_visitante'])
                    ->map(fn ($p) => [
                        'id' => $p->id_proforma,
                        'titulo' => $p->nro_proforma,
                        'subtitulo' => ($p->empresa?->razon_social ?? $p->visitante?->nombre ?? 'Sin titular')
                            .' · Bs '.number_format((float) $p->total, 2, ',', '.')." ({$p->estado})",
                        'ruta' => $this->ruta('/proformas', $p->nro_proforma),
                    ])->all()
                : [],

            'campanias' => $actor->tienePrivilegio('campanias.ver')
                ? Campania::query()
                    ->with('proforma:id_proforma,nro_proforma,id_empresa')
                    ->when($actor->esCliente(), fn ($b) => $b->whereHas('proforma', fn ($p) => $p->where('id_empresa', $actor->idEmpresa() ?? 0)))
                    ->where(fn ($w) => $w
                        ->where('nombre', 'ilike', $like)
                        ->orWhereHas('proforma', fn ($p) => $p->where('nro_proforma', 'ilike', $like)))
                    ->orderByDesc('id_campania')
                    ->limit(self::LIMITE)
                    ->get(['id_campania', 'nombre', 'estado', 'id_proforma'])
                    ->map(fn ($c) => [
                        'id' => $c->id_campania,
                        'titulo' => $c->nombre,
                        'subtitulo' => ($c->proforma?->nro_proforma ?? '')." · {$c->estado}",
                        'ruta' => $this->ruta('/campanias', $c->nombre),
                    ])->all()
                : [],
        ];
    }
}

