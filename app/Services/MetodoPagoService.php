<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\MetodoPago;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Pagos - Gestión de métodos de pago con validación Luhn (nunca guarda PAN ni CVV). */
class MetodoPagoService
{
    public function listar(array $f, int $porPagina, Usuario $actor): LengthAwarePaginator
    {
        return MetodoPago::query()
            ->with('usuario:id_usuario,nombre_completo,email')
            ->when(! $actor->esPersonal(), fn ($q) => $q->where('id_usuario', $actor->id_usuario))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('titular', 'ilike', "%{$v}%")
                ->orWhere('marca', 'ilike', "%{$v}%")
                ->orWhere('ultimos4', 'ilike', "%{$v}%")))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when(isset($f['activo']) && $f['activo'] !== '', fn ($q) => $q->where('activo', filter_var($f['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('predeterminado')
            ->orderByDesc('id_metodo')
            ->paginate($porPagina);
    }

    public function crear(array $d, Usuario $actor): MetodoPago
    {
        $tipo = $d['tipo'];
        $ultimos4 = null;
        $marca = $d['marca'] ?? null;
        $mesExp = null;
        $anioExp = null;

        if ($tipo === 'tarjeta') {
            $soloDigitos = preg_replace('/\D+/', '', (string) ($d['numero'] ?? ''));
            if (strlen($soloDigitos) < 13 || strlen($soloDigitos) > 19) {
                throw new NegocioException('El número de tarjeta debe tener entre 13 y 19 dígitos.');
            }
            if (! self::validarLuhn($soloDigitos)) {
                throw new NegocioException('El número de tarjeta no es válido (falló la verificación Luhn).');
            }
            $cvv = (string) ($d['cvv'] ?? '');
            if (! preg_match('/^\d{3,4}$/', $cvv)) {
                throw new NegocioException('El código de seguridad (CVV) debe tener 3 o 4 dígitos.');
            }
            $mesExp = (int) ($d['mes_exp'] ?? 0);
            $anioExp = (int) ($d['anio_exp'] ?? 0);
            $this->validarExpiracion($mesExp, $anioExp);

            $ultimos4 = substr($soloDigitos, -4);
            $marca = $marca ?: self::detectarMarca($soloDigitos);
        } elseif ($tipo === 'qr') {
            $marca = $marca ?: 'QR Simple';
        } elseif ($tipo === 'billetera') {
            $marca = $marca ?: 'Billetera digital';
        }

        $predeterminado = (bool) ($d['predeterminado'] ?? false);

        return DB::transaction(function () use ($actor, $tipo, $marca, $ultimos4, $d, $mesExp, $anioExp, $predeterminado) {
            $tieneActivos = MetodoPago::where('id_usuario', $actor->id_usuario)->where('activo', true)->exists();
            if (! $tieneActivos) {
                $predeterminado = true;
            }
            if ($predeterminado) {
                MetodoPago::where('id_usuario', $actor->id_usuario)->update(['predeterminado' => false]);
            }

            return MetodoPago::create([
                'id_usuario' => $actor->id_usuario,
                'tipo' => $tipo,
                'marca' => $marca,
                'ultimos4' => $ultimos4,
                'titular' => $d['titular'],
                'mes_exp' => $mesExp,
                'anio_exp' => $anioExp,
                'token_simulado' => 'tok_'.bin2hex(random_bytes(16)),
                'predeterminado' => $predeterminado,
                'activo' => true,
            ])->load('usuario:id_usuario,nombre_completo,email');
        });
    }

    public function actualizar(MetodoPago $metodo, array $d, Usuario $actor): MetodoPago
    {
        $this->exigirAcceso($metodo, $actor);

        if ($metodo->tipo === 'tarjeta') {
            $mes = (int) ($d['mes_exp'] ?? $metodo->mes_exp);
            $anio = (int) ($d['anio_exp'] ?? $metodo->anio_exp);
            $this->validarExpiracion($mes, $anio);
        }

        $predeterminado = array_key_exists('predeterminado', $d)
            ? (bool) $d['predeterminado']
            : $metodo->predeterminado;

        return DB::transaction(function () use ($metodo, $d, $predeterminado) {
            if ($predeterminado) {
                MetodoPago::where('id_usuario', $metodo->id_usuario)
                    ->where('id_metodo', '<>', $metodo->id_metodo)
                    ->update(['predeterminado' => false]);
            }

            $metodo->update([
                'titular' => $d['titular'] ?? $metodo->titular,
                'marca' => $d['marca'] ?? $metodo->marca,
                'mes_exp' => $metodo->tipo === 'tarjeta' ? (int) ($d['mes_exp'] ?? $metodo->mes_exp) : null,
                'anio_exp' => $metodo->tipo === 'tarjeta' ? (int) ($d['anio_exp'] ?? $metodo->anio_exp) : null,
                'predeterminado' => $predeterminado,
            ]);

            return $metodo->load('usuario:id_usuario,nombre_completo,email');
        });
    }

    public function cambiarEstado(MetodoPago $metodo, bool $activo, Usuario $actor): MetodoPago
    {
        $this->exigirAcceso($metodo, $actor);

        if ($metodo->activo === $activo) {
            throw new NegocioException($activo
                ? 'El método de pago ya está activo.'
                : 'El método de pago ya está dado de baja.');
        }

        $metodo->update([
            'activo' => $activo,
            'predeterminado' => $activo ? $metodo->predeterminado : false,
        ]);

        return $metodo->load('usuario:id_usuario,nombre_completo,email');
    }

    public function exigirAcceso(MetodoPago $metodo, Usuario $actor): void
    {
        if (! $actor->esPersonal() && $metodo->id_usuario !== $actor->id_usuario) {
            throw new NegocioException('No tiene acceso a este método de pago.', 403);
        }
    }

    /** Algoritmo de Luhn para validar números de tarjeta de crédito/débito. */
    public static function validarLuhn(string $numero): bool
    {
        $suma = 0;
        $duplicar = false;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $digito = (int) $numero[$i];
            if ($duplicar) {
                $digito *= 2;
                if ($digito > 9) {
                    $digito -= 9;
                }
            }
            $suma += $digito;
            $duplicar = ! $duplicar;
        }

        return ($suma % 10) === 0;
    }

    private static function detectarMarca(string $numero): string
    {
        if (str_starts_with($numero, '4')) {
            return 'Visa';
        }
        if (preg_match('/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/', $numero)) {
            return 'Mastercard';
        }
        if (preg_match('/^3[47]/', $numero)) {
            return 'American Express';
        }

        return 'Tarjeta';
    }

    private function validarExpiracion(int $mes, int $anio): void
    {
        if ($mes < 1 || $mes > 12) {
            throw new NegocioException('El mes de expiración debe estar entre 1 y 12.');
        }
        $anioActual = (int) now()->format('Y');
        $mesActual = (int) now()->format('n');
        if ($anio < $anioActual || ($anio === $anioActual && $mes < $mesActual)) {
            throw new NegocioException('La tarjeta ingresada se encuentra vencida.');
        }
    }
}

