<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\EmpresaAnunciante;
use App\Models\Paquete;
use App\Models\Proforma;
use App\Models\ServicioPublicitario;
use App\Models\Usuario;

/**
 * B6 - Búsqueda global predictiva en empresas, servicios, paquetes,
 * campañas y proformas. Respeta privilegios RBAC y filtro "solo lo suyo".
 * Máximo 5 resultados por tipo.
 */
class BusquedaService
{
    private const LIMITE = 5;

    public function buscar(string $termino, Usuario $actor): array
    {
        $q = trim($termino);
        if (mb_strlen($q) < 2) {
            return [
                'empresas' => [],
                'servicios' => [],
                'paquetes' => [],
                'proformas' => [],
                'campanias' => [],
            ];
        }

        $like = "%{$q}%";

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
                        'ruta' => '/empresas',
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
                        'ruta' => '/servicios',
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
                        'ruta' => '/paquetes',
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
                        'ruta' => '/proformas',
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
                        'ruta' => '/campanias',
                    ])->all()
                : [],
        ];
    }
}

