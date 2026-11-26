<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\MetodoPago;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Pagos - Métodos de pago: tarjetas registradas con Stripe (Stripe Elements + SetupIntent).
 * Nunca se guarda el número completo ni el CVV; solo el id de Stripe, la marca, los últimos 4 y el vencimiento.
 */
class MetodoPagoService
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

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

    /** Paso 1: el navegador pide un SetupIntent para registrar la tarjeta con Stripe Elements. */
    public function prepararRegistro(Usuario $actor): array
    {
        return $this->stripe->prepararRegistro($actor);
    }

    /**
     * Paso 2: la tarjeta ya quedó guardada en Stripe; se registra aquí con los datos que Stripe informa
     * (marca, últimos 4 dígitos y vencimiento). El número completo y el CVV nunca pasan por el servidor.
     */
    public function crear(array $d, Usuario $actor): MetodoPago
    {
        $pm = (string) $d['payment_method'];
        if (MetodoPago::where('stripe_payment_method', $pm)->exists()) {
            throw new NegocioException('Esa tarjeta ya está registrada.');
        }
        $tarjeta = $this->stripe->datosTarjeta($actor, $pm);
        $predeterminado = (bool) ($d['predeterminado'] ?? false);

        return DB::transaction(function () use ($actor, $d, $pm, $tarjeta, $predeterminado) {
            $tieneActivos = MetodoPago::where('id_usuario', $actor->id_usuario)->where('activo', true)->whereNotNull('stripe_payment_method')->exists();
            if (! $tieneActivos) {
                $predeterminado = true;
            }
            if ($predeterminado) {
                MetodoPago::where('id_usuario', $actor->id_usuario)->update(['predeterminado' => false]);
            }

            return MetodoPago::create([
                'id_usuario' => $actor->id_usuario,
                'tipo' => 'tarjeta',
                'marca' => $tarjeta['marca'],
                'ultimos4' => $tarjeta['ultimos4'],
                'titular' => $d['titular'],
                'mes_exp' => $tarjeta['mes_exp'],
                'anio_exp' => $tarjeta['anio_exp'],
                'token_simulado' => $pm, // columna obligatoria del Proyecto 2: guarda el mismo id de Stripe
                'stripe_payment_method' => $pm,
                'predeterminado' => $predeterminado,
                'activo' => true,
            ])->load('usuario:id_usuario,nombre_completo,email');
        });
    }

    public function actualizar(MetodoPago $metodo, array $d, Usuario $actor): MetodoPago
    {
        $this->exigirAcceso($metodo, $actor);

        $predeterminado = array_key_exists('predeterminado', $d)
            ? (bool) $d['predeterminado']
            : $metodo->predeterminado;

        return DB::transaction(function () use ($metodo, $d, $predeterminado) {
            if ($predeterminado) {
                MetodoPago::where('id_usuario', $metodo->id_usuario)
                    ->where('id_metodo', '<>', $metodo->id_metodo)
                    ->update(['predeterminado' => false]);
            }

            // Marca, últimos 4 dígitos y vencimiento los informa Stripe: aquí solo se edita el titular
            $metodo->update([
                'titular' => $d['titular'] ?? $metodo->titular,
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

        if ($activo) {
            // Una tarjeta dada de baja se desvinculó de Stripe; para usarla hay que registrarla otra vez
            throw new NegocioException('Una tarjeta dada de baja no se puede reactivar: regístrela de nuevo en «Agregar tarjeta».');
        }
        $this->stripe->quitarTarjeta($metodo);
        $metodo->update([
            'activo' => false,
            'predeterminado' => false,
        ]);

        return $metodo->load('usuario:id_usuario,nombre_completo,email');
    }

    public function exigirAcceso(MetodoPago $metodo, Usuario $actor): void
    {
        if (! $actor->esPersonal() && $metodo->id_usuario !== $actor->id_usuario) {
            throw new NegocioException('No tiene acceso a este método de pago.', 403);
        }
    }
}
