<?php

namespace App\Services;

use App\Models\Visitante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** CU3 - Visitantes que solicitan cotizaciones públicas. */
class VisitanteService
{
    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return Visitante::query()
            ->withCount('proformas')
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nombre', 'ilike', "%{$v}%")
                ->orWhere('email', 'ilike', "%{$v}%")
                ->orWhere('telefono', 'ilike', "%{$v}%")))
            ->orderByDesc('id_visitante')
            ->paginate($porPagina);
    }

    public function obtener(Visitante $visitante): Visitante
    {
        return $visitante->load(['proformas' => fn ($q) => $q->orderByDesc('id_proforma')]);
    }
}

