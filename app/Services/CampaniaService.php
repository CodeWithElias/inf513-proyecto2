<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Campania;
use App\Models\Proforma;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** CU3 - Campañas publicitarias (portado de NCampania). */
class CampaniaService
{
    private const RELACIONES = [
        'proforma:id_proforma,nro_proforma,total,estado,id_empresa,id_visitante',
        'proforma.empresa:id_empresa,razon_social,nit',
        'proforma.visitante:id_visitante,nombre',
    ];

    public function listar(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        return Campania::query()
            ->with(self::RELACIONES)
            ->when($actor->esCliente(), fn ($q) => $q->whereHas('proforma', fn ($p) => $actor->filtrarProformas($p)))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('nombre', 'ilike', "%{$v}%")
                ->orWhereHas('proforma', fn ($p) => $p->where('nro_proforma', 'ilike', "%{$v}%"))))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->orderByDesc('id_campania')
            ->paginate($porPagina);
    }

    public function obtener(Campania $campania, Usuario $actor): Campania
    {
        $campania->load(self::RELACIONES);
        if ($actor->esCliente() && ! $actor->esSuProforma($campania->proforma)) {
            throw new NegocioException('No tiene acceso a esta campaña: pertenece a otra empresa.', 403);
        }

        return $campania;
    }

    public function crear(array $d): Campania
    {
        $proforma = Proforma::find($d['id_proforma']);
        if (! $proforma) {
            throw new NegocioException('La proforma seleccionada no existe.');
        }
        if ($proforma->estado !== 'aprobada') {
            throw new NegocioException("Solo se puede crear una campaña a partir de una proforma aprobada (estado actual: {$proforma->estado}).");
        }
        if (Campania::where('id_proforma', $proforma->id_proforma)->exists()) {
            throw new NegocioException("La proforma {$proforma->nro_proforma} ya tiene una campaña registrada.");
        }
        $this->validarFechas($d['fecha_inicio'] ?? null, $d['fecha_fin'] ?? null);

        return Campania::create([
            'nombre' => $d['nombre'],
            'fecha_inicio' => $d['fecha_inicio'] ?? null,
            'fecha_fin' => $d['fecha_fin'] ?? null,
            'estado' => $d['estado'] ?? 'activa',
            'id_proforma' => $proforma->id_proforma,
        ])->load(self::RELACIONES);
    }

    public function actualizar(Campania $campania, array $d): Campania
    {
        $this->validarFechas($d['fecha_inicio'] ?? null, $d['fecha_fin'] ?? null);

        $campania->update([
            'nombre' => $d['nombre'],
            'fecha_inicio' => $d['fecha_inicio'] ?? null,
            'fecha_fin' => $d['fecha_fin'] ?? null,
            'estado' => $d['estado'] ?? $campania->estado,
        ]);

        return $campania->load(self::RELACIONES);
    }

    private function validarFechas(?string $inicio, ?string $fin): void
    {
        if ($inicio && $fin && $fin < $inicio) {
            throw new NegocioException('La fecha de finalización de la campaña no puede ser anterior a la fecha de inicio.');
        }
    }
}

