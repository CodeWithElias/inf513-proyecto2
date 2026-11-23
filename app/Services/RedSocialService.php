<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\CanalDifusion;
use App\Models\Plataforma;
use App\Models\RedSocial;
use App\Models\SeguidoresRed;
use App\Models\UnidadUniversitaria;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU2 - Redes sociales institucionales (portado de NRedSocial y NSeguidoresRed).
 * El Encargado de Unidad solo ve las redes de su unidad o de las que es responsable.
 */
class RedSocialService
{
    private const RELACIONES = [
        'canal:id_canal,nombre,activo,fecha_alta,fecha_baja',
        'plataforma:id_plataforma,nombre',
        'unidad:id_unidad,nombre',
        'responsable:id_usuario,nombre_completo,email',
    ];

    public function listar(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        return $this->visibles($actor)
            ->with(self::RELACIONES)
            ->addSelect(['ultimos_seguidores' => SeguidoresRed::select('seguidores')
                ->whereColumn('seguidores_red.id_canal', 'red_social.id_canal')
                ->orderByDesc('fecha')->orderByDesc('id_seguidores')->limit(1)])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('url', 'ilike', "%{$v}%")
                ->orWhereHas('canal', fn ($c) => $c->where('nombre', 'ilike', "%{$v}%"))))
            ->when($f['id_plataforma'] ?? null, fn ($q, $v) => $q->where('id_plataforma', $v))
            ->when($f['id_unidad'] ?? null, fn ($q, $v) => $q->where('id_unidad', $v))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->whereHas('canal',
                fn ($c) => $c->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN))))
            ->orderBy('id_canal')
            ->paginate($porPagina);
    }

    public function obtener(RedSocial $red, Usuario $actor): RedSocial
    {
        $this->exigirAcceso($red, $actor);

        return $red->load(self::RELACIONES);
    }

    public function crear(array $d): RedSocial
    {
        $this->validarRelaciones($d);
        $this->validarUnicos($d['nombre'], $d['url'], 0);

        return DB::transaction(function () use ($d) {
            $canal = CanalDifusion::create(['tipo' => CanalDifusion::RED_SOCIAL, 'nombre' => $d['nombre']]);

            return RedSocial::create([
                'id_canal' => $canal->id_canal,
                'url' => $d['url'],
                'id_plataforma' => $d['id_plataforma'],
                'id_usuario' => $d['id_usuario'] ?? null,
                'id_unidad' => $d['id_unidad'] ?? null,
            ])->load(self::RELACIONES);
        });
    }

    public function actualizar(RedSocial $red, array $d): RedSocial
    {
        $this->validarRelaciones($d);
        $this->validarUnicos($d['nombre'], $d['url'], $red->id_canal);

        DB::transaction(function () use ($red, $d) {
            $red->canal->update(['nombre' => $d['nombre']]);
            $red->update([
                'url' => $d['url'],
                'id_plataforma' => $d['id_plataforma'],
                'id_usuario' => $d['id_usuario'] ?? null,
                'id_unidad' => $d['id_unidad'] ?? null,
            ]);
        });

        return $red->load(self::RELACIONES);
    }

    public function cambiarEstado(RedSocial $red, bool $activo): RedSocial
    {
        $canal = $red->canal;
        if ($canal->activo === $activo) {
            throw new NegocioException($activo ? "La red social {$canal->nombre} ya está activa." : "La red social {$canal->nombre} ya está dada de baja.");
        }
        $canal->update(['activo' => $activo, 'fecha_baja' => $activo ? null : now()->toDateString()]);

        return $red->load(self::RELACIONES);
    }

    public function seguidores(RedSocial $red, Usuario $actor): Collection
    {
        $this->exigirAcceso($red, $actor);

        return $red->seguidores()->orderBy('fecha')->orderBy('id_seguidores')->get();
    }

    public function registrarSeguidores(RedSocial $red, int $cantidad, ?string $fecha, Usuario $actor): SeguidoresRed
    {
        $this->exigirAcceso($red, $actor);
        if (! $red->canal->activo) {
            throw new NegocioException("La red social {$red->canal->nombre} está inactiva. Reactívela antes de registrar métricas de seguidores.");
        }

        return SeguidoresRed::create([
            'id_canal' => $red->id_canal,
            'seguidores' => $cantidad,
            'fecha' => $fecha ?: now()->toDateString(),
        ]);
    }

    /** Consulta base con el filtro "solo lo suyo" del Encargado de Unidad. */
    private function visibles(Usuario $actor): Builder
    {
        $q = RedSocial::query()->select('red_social.*');
        if ($actor->esEncargado()) {
            $q->where(fn ($w) => $w->where('id_usuario', $actor->id_usuario)
                ->when($actor->id_unidad, fn ($x) => $x->orWhere('id_unidad', $actor->id_unidad)));
        }

        return $q;
    }

    private function exigirAcceso(RedSocial $red, Usuario $actor): void
    {
        if (! $actor->esEncargado()) {
            return;
        }
        $esSuya = $red->id_usuario === $actor->id_usuario
            || ($actor->id_unidad !== null && $red->id_unidad === $actor->id_unidad);
        if (! $esSuya) {
            throw new NegocioException('No tiene acceso a esta red social: no es de su unidad ni está a su cargo.', 403);
        }
    }

    private function validarRelaciones(array $d): void
    {
        if (! Plataforma::whereKey($d['id_plataforma'])->exists()) {
            throw new NegocioException('La plataforma seleccionada no existe.');
        }
        if (! empty($d['id_usuario'])) {
            $u = Usuario::find($d['id_usuario']);
            if (! $u) {
                throw new NegocioException('El responsable seleccionado no existe.');
            }
            if (! $u->activo) {
                throw new NegocioException("El usuario {$u->nombre_completo} está inactivo. Debe estar activo para ser responsable de una red social.");
            }
        }
        if (! empty($d['id_unidad'])) {
            $un = UnidadUniversitaria::find($d['id_unidad']);
            if (! $un) {
                throw new NegocioException('La unidad universitaria seleccionada no existe.');
            }
            if (! $un->activo) {
                throw new NegocioException("La unidad universitaria {$un->nombre} está inactiva.");
            }
        }
    }

    private function validarUnicos(string $nombre, string $url, int $idCanal): void
    {
        $nombreEnUso = CanalDifusion::where('tipo', CanalDifusion::RED_SOCIAL)
            ->whereRaw('lower(nombre) = lower(?)', [$nombre])
            ->where('id_canal', '<>', $idCanal)->exists();
        if ($nombreEnUso) {
            throw new NegocioException("Ya existe otra red social con el nombre «{$nombre}».");
        }
        $urlEnUso = RedSocial::whereRaw('lower(url) = lower(?)', [$url])->where('id_canal', '<>', $idCanal)->exists();
        if ($urlEnUso) {
            throw new NegocioException("Ya existe otra red social con la URL «{$url}».");
        }
    }
}
