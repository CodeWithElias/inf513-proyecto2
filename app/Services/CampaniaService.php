<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Campania;
use App\Models\EspacioPublicitario;
use App\Models\ProgramacionPauta;
use Illuminate\Support\Facades\DB;
use App\Models\Proforma;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** CU3 - Campañas publicitarias (portado de NCampania). */
class CampaniaService
{
    public function __construct(private readonly AvisoService $avisos)
    {
    }

    private const RELACIONES = [
        'proforma:id_proforma,nro_proforma,total,estado,id_empresa,id_visitante',
        'proforma.empresa:id_empresa,razon_social,nit',
        'proforma.visitante:id_visitante,nombre',
    ];

    public function listar(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        $this->finalizarVencidas();

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

    /**
     * Campaña que se abre sola al aprobarse una proforma (si aún no tiene).
     * El personal puede luego cambiar su nombre y fechas.
     */
    public function crearDesdeProforma(Proforma $proforma): ?Campania
    {
        if (Campania::where('id_proforma', $proforma->id_proforma)->exists()) {
            return null;
        }
        $proforma->loadMissing(['empresa:id_empresa,razon_social', 'visitante:id_visitante,nombre']);
        $cliente = $proforma->empresa?->razon_social ?? $proforma->visitante?->nombre ?? 'Cliente';

        return Campania::create([
            'nombre' => mb_substr("Campaña {$cliente} · {$proforma->nro_proforma}", 0, 200),
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => null,
            'estado' => 'activa',
            'id_proforma' => $proforma->id_proforma,
        ]);
    }

    public function actualizar(Campania $campania, array $d, ?Usuario $actor = null): Campania
    {
        $this->validarFechas($d['fecha_inicio'] ?? null, $d['fecha_fin'] ?? null);
        $estado = $d['estado'] ?? $campania->estado;
        $programadas = $this->pautasProgramadas($campania);

        // No dejar pautas programadas fuera de las nuevas fechas
        if ($programadas->isNotEmpty() && (! empty($d['fecha_inicio']) || ! empty($d['fecha_fin']))) {
            $fuera = $programadas->filter(fn ($p) => (! empty($d['fecha_inicio']) && $p->fecha < $d['fecha_inicio'])
                || (! empty($d['fecha_fin']) && $p->fecha > $d['fecha_fin']));
            if ($fuera->isNotEmpty()) {
                throw new NegocioException("Hay {$fuera->count()} pauta(s) programada(s) fuera de las nuevas fechas. Reprográmelas o cancélelas antes de cambiar las fechas.");
            }
        }
        if ($estado === 'finalizada' && $campania->estado !== 'finalizada' && $programadas->isNotEmpty()) {
            throw new NegocioException("La campaña tiene {$programadas->count()} pauta(s) programada(s) sin emitir. Emítalas o cancélelas antes de finalizarla.");
        }

        DB::transaction(function () use ($campania, $d, $estado, $programadas, $actor) {
            $cancelar = $estado === 'cancelada' && $campania->estado !== 'cancelada';
            $campania->update([
                'nombre' => $d['nombre'],
                'fecha_inicio' => $d['fecha_inicio'] ?? null,
                'fecha_fin' => $d['fecha_fin'] ?? null,
                'estado' => $estado,
            ]);

            if ($cancelar) {
                // Cancelar la campaña libera sus espacios: sus pautas programadas se cancelan
                foreach ($programadas as $p) {
                    DB::table('espacio_publicitario')->where('id_espacio', $p->id_espacio)->update(['estado' => EspacioPublicitario::ESTADO_DISPONIBLE]);
                    DB::table('programacion_pauta')->where('id_programacion', $p->id_programacion)->update(['estado' => ProgramacionPauta::ESTADO_CANCELADA]);
                }
                $campania->loadMissing('proforma');
                $extra = $programadas->isNotEmpty() ? " Se cancelaron sus {$programadas->count()} pauta(s) programada(s)." : '';
                $this->avisos->alCliente($campania->proforma, "Campaña cancelada: {$campania->nombre}",
                    "Canal 11 canceló la campaña «{$campania->nombre}».{$extra} Si tiene dudas, comuníquese con el canal.", $actor);
            }
        });

        return $campania->load(self::RELACIONES);
    }

    /** Pautas programadas (aún sin emitir) de la campaña, con la fecha de su espacio. */
    private function pautasProgramadas(Campania $campania)
    {
        return DB::table('programacion_pauta as pp')
            ->join('material_publicitario as m', 'm.id_material', '=', 'pp.id_material')
            ->join('espacio_publicitario as e', 'e.id_espacio', '=', 'pp.id_espacio')
            ->where('m.id_campania', $campania->id_campania)
            ->where('pp.estado', ProgramacionPauta::ESTADO_PROGRAMADA)
            ->get(['pp.id_programacion', 'pp.id_espacio', DB::raw('e.fecha::text as fecha')]);
    }

    /**
     * Campañas activas o pausadas cuya fecha de fin ya pasó y no tienen pautas pendientes:
     * se cierran solas (así no quedan "activas" para siempre).
     */
    public function finalizarVencidas(): int
    {
        return Campania::query()
            ->whereIn('estado', ['activa', 'pausada'])
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', now()->toDateString())
            ->whereDoesntHave('materiales.pautas', fn ($q) => $q->where('estado', ProgramacionPauta::ESTADO_PROGRAMADA))
            ->update(['estado' => 'finalizada']);
    }

    private function validarFechas(?string $inicio, ?string $fin): void
    {
        if ($inicio && $fin && $fin < $inicio) {
            throw new NegocioException('La fecha de finalización de la campaña no puede ser anterior a la fecha de inicio.');
        }
    }
}

