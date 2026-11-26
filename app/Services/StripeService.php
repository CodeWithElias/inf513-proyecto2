<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Exceptions\PagoRequiereAccion;
use App\Models\MetodoPago;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * Pasarela de pagos con Stripe (modo de prueba).
 *  - Las tarjetas se registran en el navegador con Stripe Elements (SetupIntent): el número y el CVV
 *    nunca llegan a nuestro servidor; aquí solo se guarda el id de la tarjeta en Stripe (pm_…).
 *  - Cada cobro es un PaymentIntent. Si el banco pide verificación (3D Secure) se lanza
 *    PagoRequiereAccion: el navegador la completa y vuelve a enviar el pago con el id del PaymentIntent,
 *    que aquí se verifica (estado, monto, moneda, cliente y que no se haya usado antes).
 * Tarjetas de prueba: 4242 4242 4242 4242 (aprobada), 4000 0000 0000 0341 (se registra; el banco rechaza al cobrar),
 * 4000 0027 6000 3184 (pide 3D Secure en cada pago), 4000 0000 0000 9995 (sin fondos: Stripe la rechaza al registrarla).
 */
class StripeService
{
    private ?StripeClient $cliente = null;

    public function configurado(): bool
    {
        return filled(config('services.stripe.secret')) && filled(config('services.stripe.key'));
    }

    private function stripe(): StripeClient
    {
        if (! $this->configurado()) {
            throw new NegocioException('Los pagos en línea aún no están configurados: faltan las claves de Stripe (STRIPE_KEY y STRIPE_SECRET). Avise al administrador.', 503);
        }

        return $this->cliente ??= new StripeClient(['api_key' => config('services.stripe.secret'), 'max_network_retries' => 2]);
    }

    /** Cliente (Customer) de Stripe del usuario; se crea la primera vez. */
    public function clienteDe(Usuario $u): string
    {
        $id = DB::table('stripe_cliente')->where('id_usuario', $u->id_usuario)->value('customer_id');
        if ($id) {
            return $id;
        }
        $c = $this->llamar(fn () => $this->stripe()->customers->create([
            'email' => $u->email, 'name' => $u->nombre_completo, 'metadata' => ['id_usuario' => $u->id_usuario],
        ]));
        DB::table('stripe_cliente')->insert(['id_usuario' => $u->id_usuario, 'customer_id' => $c->id, 'fecha_registro' => now()]);

        return $c->id;
    }

    /** Paso 1 del registro de una tarjeta: el navegador la confirma con este client_secret. */
    public function prepararRegistro(Usuario $u): array
    {
        $si = $this->llamar(fn () => $this->stripe()->setupIntents->create([
            'customer' => $this->clienteDe($u), 'usage' => 'off_session',
            // Solo tarjetas, sin métodos que redirigen a otra página
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            'metadata' => ['id_usuario' => $u->id_usuario],
        ]));

        return ['client_secret' => $si->client_secret];
    }

    /** Paso 2: datos de la tarjeta ya guardada en Stripe (debe pertenecer al cliente del usuario). */
    public function datosTarjeta(Usuario $u, string $idPaymentMethod): array
    {
        $pm = $this->llamar(fn () => $this->stripe()->paymentMethods->retrieve($idPaymentMethod));
        if ($pm->customer !== $this->clienteDe($u) || $pm->type !== 'card') {
            throw new NegocioException('La tarjeta no pertenece a su cuenta. Vuelva a registrarla.', 403);
        }

        return [
            'marca' => ucfirst((string) $pm->card->brand), 'ultimos4' => $pm->card->last4,
            'mes_exp' => $pm->card->exp_month, 'anio_exp' => $pm->card->exp_year,
        ];
    }

    public function quitarTarjeta(MetodoPago $m): void
    {
        if (! $m->stripe_payment_method || ! $this->configurado()) {
            return;
        }
        try {
            $this->stripe()->paymentMethods->detach($m->stripe_payment_method);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe: no se pudo desvincular la tarjeta '.$m->stripe_payment_method.': '.$e->getMessage());
        }
    }

    /**
     * Cobra $monto (en bolivianos) con la tarjeta del método. Si se recibe $paymentIntent, en vez de cobrar
     * de nuevo se verifica ese cobro (ya autorizado tras 3D Secure).
     *
     * @return array{estado: string, codigo: ?string, mensaje: string, payment_intent: ?string}
     */
    public function cobrar(MetodoPago $m, float $monto, string $descripcion, array $meta, ?string $paymentIntent = null): array
    {
        if (! $m->stripe_payment_method) {
            throw new NegocioException('Este método de pago se registró antes de usar Stripe y no se puede cobrar. Registre de nuevo su tarjeta en «Métodos de pago».');
        }
        $centavos = (int) round($monto * 100);
        $moneda = strtolower((string) config('services.stripe.moneda', 'bob'));
        $customer = DB::table('stripe_cliente')->where('id_usuario', $m->id_usuario)->value('customer_id');

        if ($paymentIntent) {
            $pi = $this->llamar(fn () => $this->stripe()->paymentIntents->retrieve($paymentIntent));
            if ($pi->amount !== $centavos || $pi->currency !== $moneda || $pi->customer !== $customer
                || $pi->payment_method !== $m->stripe_payment_method || ($pi->metadata['concepto'] ?? null) !== ($meta['concepto'] ?? null)) {
                throw new NegocioException('El pago verificado no corresponde a esta operación. Inténtelo de nuevo.', 409);
            }
            if (DB::table('transaccion_pago')->where('stripe_payment_intent', $pi->id)->where('estado', 'aprobada')->exists()) {
                throw new NegocioException('Este pago ya fue registrado.', 409);
            }

            return $this->resultado($pi);
        }

        try {
            $pi = $this->stripe()->paymentIntents->create([
                'amount' => $centavos, 'currency' => $moneda, 'customer' => $customer,
                'payment_method' => $m->stripe_payment_method,
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'confirm' => true, 'description' => $descripcion,
                'metadata' => $meta + ['sistema' => 'TVU Canal 11'],
            ]);
        } catch (CardException $e) {
            // Rechazo del banco (fondos, vencida, CVC…): se registra como transacción rechazada
            return ['estado' => 'rechazada', 'codigo' => null, 'mensaje' => $this->motivo($e->getDeclineCode() ?? $e->getStripeCode(), $e->getMessage()),
                'payment_intent' => $e->getError()?->payment_intent?->id];
        } catch (ApiErrorException $e) {
            $this->fallaApi($e);
        }

        return $this->resultado($pi);
    }

    public function reembolsar(?string $paymentIntent): void
    {
        if (! $paymentIntent) {
            return;
        }
        try {
            $this->stripe()->refunds->create(['payment_intent' => $paymentIntent, 'reason' => 'requested_by_customer']);
        } catch (ApiErrorException $e) {
            // Queda en el log para devolverlo a mano desde el panel de Stripe
            Log::critical("Stripe: NO se pudo reembolsar {$paymentIntent}: ".$e->getMessage());
        }
    }

    private function resultado(PaymentIntent $pi): array
    {
        return match ($pi->status) {
            'succeeded' => ['estado' => 'aprobada', 'codigo' => $pi->id, 'mensaje' => 'Pago aprobado por Stripe.', 'payment_intent' => $pi->id],
            'requires_action' => throw new PagoRequiereAccion($pi->client_secret, $pi->id),
            'processing' => throw new NegocioException('Stripe todavía está procesando el pago. Espere unos segundos y vuelva a intentarlo.', 409),
            default => ['estado' => 'rechazada', 'codigo' => null, 'payment_intent' => $pi->id,
                'mensaje' => $this->motivo($pi->last_payment_error?->decline_code ?? $pi->last_payment_error?->code, 'La verificación de la tarjeta no se completó.')],
        };
    }

    private function motivo(?string $codigo, string $original): string
    {
        return match ($codigo) {
            'insufficient_funds' => 'Fondos insuficientes.',
            'expired_card' => 'Tarjeta vencida.',
            'incorrect_cvc', 'invalid_cvc' => 'El código de seguridad (CVC) es incorrecto.',
            'lost_card', 'stolen_card' => 'La tarjeta fue reportada como perdida o robada.',
            'processing_error' => 'Error del banco al procesar la tarjeta. Inténtelo de nuevo.',
            'authentication_required', 'payment_intent_authentication_failure' => 'No se completó la verificación de la tarjeta (3D Secure).',
            'card_declined', 'generic_decline', 'do_not_honor' => 'El banco rechazó la tarjeta.',
            default => 'Pago rechazado: '.mb_substr($original, 0, 150),
        };
    }

    /** Ejecuta una llamada a Stripe traduciendo los errores técnicos a mensajes en español. */
    private function llamar(callable $fn)
    {
        try {
            return $fn();
        } catch (CardException $e) {
            throw new NegocioException($this->motivo($e->getDeclineCode() ?? $e->getStripeCode(), $e->getMessage()));
        } catch (ApiErrorException $e) {
            $this->fallaApi($e);
        }
    }

    private function fallaApi(ApiErrorException $e): never
    {
        Log::error('Stripe: '.$e::class.' '.$e->getMessage());
        throw match (true) {
            $e instanceof AuthenticationException => new NegocioException('Las claves de Stripe no son válidas. Avise al administrador.', 503),
            $e instanceof ApiConnectionException => new NegocioException('No se pudo conectar con Stripe. Revise la conexión a internet e inténtelo de nuevo.', 503),
            $e instanceof InvalidRequestException && str_contains($e->getMessage(), 'No such') => new NegocioException('La tarjeta o el cobro indicado no existe en Stripe. Vuelva a intentarlo.', 422),
            default => new NegocioException('Stripe no pudo procesar la operación: '.mb_substr($e->getMessage(), 0, 160), 502),
        };
    }
}
