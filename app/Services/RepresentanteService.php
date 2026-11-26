<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\EmpresaAnunciante;
use App\Models\Representante;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** CU1 - Clientes = representantes de empresas anunciantes (portado de NRepresentante). */
class RepresentanteService
{
    public function listar(array $f, int $porPagina): LengthAwarePaginator
    {
        return Representante::query()
            ->with(['usuario:id_usuario,nombre_completo,email,telefono,activo', 'empresa:id_empresa,razon_social,nit'])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('ci', 'ilike', "%{$v}%")
                ->orWhereHas('usuario', fn ($u) => $u->where('nombre_completo', 'ilike', "%{$v}%")->orWhere('email', 'ilike', "%{$v}%"))
                ->orWhereHas('empresa', fn ($e) => $e->where('razon_social', 'ilike', "%{$v}%"))))
            ->when($f['id_empresa'] ?? null, fn ($q, $v) => $q->where('id_empresa', $v))
            ->orderBy('id_usuario')
            ->paginate($porPagina);
    }

    public function crear(array $d): Representante
    {
        $usuario = Usuario::find($d['id_usuario']);
        if (! $usuario) {
            throw new NegocioException('El usuario seleccionado no existe. Regístrelo primero como usuario.');
        }
        if (! $usuario->activo) {
            throw new NegocioException("El usuario {$usuario->nombre_completo} está inactivo. Reactívelo primero.");
        }
        if (! $usuario->esCliente()) {
            throw new NegocioException("El usuario {$usuario->nombre_completo} tiene el rol {$usuario->rol?->nombre}; solo un usuario con rol Cliente puede representar a una empresa.");
        }
        if (Representante::find($usuario->id_usuario)) {
            throw new NegocioException("El usuario {$usuario->nombre_completo} ya es cliente. Modifique sus datos de cliente en su lugar.");
        }
        $this->validarEmpresa((int) $d['id_empresa']);

        return Representante::create([
            'id_usuario' => $usuario->id_usuario,
            'ci' => $d['ci'],
            'cargo' => $d['cargo'] ?? null,
            'id_empresa' => $d['id_empresa'],
        ])->load(['usuario:id_usuario,nombre_completo,email,telefono,activo', 'empresa:id_empresa,razon_social,nit']);
    }

    public function actualizar(Representante $r, array $d): Representante
    {
        $this->validarEmpresa((int) $d['id_empresa']);
        $r->update(['ci' => $d['ci'], 'cargo' => $d['cargo'] ?? null, 'id_empresa' => $d['id_empresa']]);

        return $r->load(['usuario:id_usuario,nombre_completo,email,telefono,activo', 'empresa:id_empresa,razon_social,nit']);
    }

    public function eliminar(Representante $r): void
    {
        $r->delete();
    }

    private function validarEmpresa(int $id): void
    {
        $empresa = EmpresaAnunciante::find($id);
        if (! $empresa) {
            throw new NegocioException('La empresa seleccionada no existe.');
        }
        if (! $empresa->activo) {
            throw new NegocioException("La empresa {$empresa->razon_social} está inactiva.");
        }
    }
}
