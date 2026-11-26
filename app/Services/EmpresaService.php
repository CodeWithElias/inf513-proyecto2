<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\EmpresaAnunciante;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** CU3 - Empresas anunciantes (portado de NEmpresa). */
class EmpresaService
{
    public function listar(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        return EmpresaAnunciante::query()
            ->withCount(['representantes', 'proformas'])
            ->when($actor->esCliente(), fn ($q) => $q->where('id_empresa', $actor->idEmpresa() ?? 0))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('razon_social', 'ilike', "%{$v}%")
                ->orWhere('nit', 'ilike', "%{$v}%")
                ->orWhere('nombre_comercial', 'ilike', "%{$v}%")))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('id_empresa')
            ->paginate($porPagina);
    }

    public function obtener(EmpresaAnunciante $empresa, Usuario $actor): EmpresaAnunciante
    {
        if ($actor->esCliente() && $empresa->id_empresa !== $actor->idEmpresa()) {
            throw new NegocioException('No tiene acceso a los datos de otra empresa.', 403);
        }

        return $empresa->load(['representantes.usuario:id_usuario,nombre_completo,email,activo'])
            ->loadCount(['representantes', 'proformas']);
    }

    public function crear(array $d): EmpresaAnunciante
    {
        $this->validarNitUnico($d['nit'], 0);

        return EmpresaAnunciante::create([
            'razon_social' => $d['razon_social'],
            'nit' => $d['nit'],
            'nombre_comercial' => $d['nombre_comercial'] ?? null,
            'direccion_facturacion' => $d['direccion_facturacion'] ?? null,
            'email_facturacion' => isset($d['email_facturacion']) ? mb_strtolower($d['email_facturacion']) : null,
            'telefono' => $d['telefono'] ?? null,
            'activo' => true,
        ]);
    }

    public function actualizar(EmpresaAnunciante $empresa, array $d): EmpresaAnunciante
    {
        $this->validarNitUnico($d['nit'], $empresa->id_empresa);

        $empresa->update([
            'razon_social' => $d['razon_social'],
            'nit' => $d['nit'],
            'nombre_comercial' => $d['nombre_comercial'] ?? null,
            'direccion_facturacion' => $d['direccion_facturacion'] ?? null,
            'email_facturacion' => isset($d['email_facturacion']) ? mb_strtolower($d['email_facturacion']) : null,
            'telefono' => $d['telefono'] ?? null,
        ]);

        return $empresa;
    }

    public function cambiarEstado(EmpresaAnunciante $empresa, bool $activo): EmpresaAnunciante
    {
        if ($empresa->activo === $activo) {
            throw new NegocioException($activo
                ? "La empresa {$empresa->razon_social} ya está activa."
                : "La empresa {$empresa->razon_social} ya está dada de baja.");
        }
        if (! $activo) {
            $pendientes = DB::table('proforma')->where('id_empresa', $empresa->id_empresa)->where('estado', 'pendiente')->count();
            if ($pendientes > 0) {
                throw new NegocioException("No se puede dar de baja la empresa {$empresa->razon_social}: tiene {$pendientes} proforma(s) pendiente(s). Apruébelas o recházelas primero.");
            }
            $enCurso = DB::table('campania as c')->join('proforma as p', 'p.id_proforma', '=', 'c.id_proforma')
                ->where('p.id_empresa', $empresa->id_empresa)->whereIn('c.estado', ['activa', 'pausada'])->count();
            if ($enCurso > 0) {
                throw new NegocioException("No se puede dar de baja la empresa {$empresa->razon_social}: tiene {$enCurso} campaña(s) en curso. Finalícelas o cancélelas primero.");
            }
        }
        $empresa->update(['activo' => $activo]);

        return $empresa;
    }

    private function validarNitUnico(string $nit, int $idEmpresa): void
    {
        $existe = EmpresaAnunciante::whereRaw('lower(nit) = lower(?)', [$nit])
            ->where('id_empresa', '<>', $idEmpresa)
            ->exists();
        if ($existe) {
            throw new NegocioException("Ya existe una empresa registrada con el NIT «{$nit}».");
        }
    }
}

