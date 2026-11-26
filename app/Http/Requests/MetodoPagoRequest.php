<?php

namespace App\Http\Requests;

/**
 * Métodos de pago con Stripe: el navegador registra la tarjeta en Stripe (Stripe Elements) y aquí solo
 * llega el id de la tarjeta (pm_…). Nunca se reciben el número ni el CVV.
 */
class MetodoPagoRequest extends ApiRequest
{
    public function rules(): array
    {
        if (! $this->isMethod('POST')) {
            return [
                'titular' => ['required', 'string', 'min:3', 'max:150'],
                'predeterminado' => ['nullable', 'boolean'],
            ];
        }

        return [
            'payment_method' => ['required', 'string', 'starts_with:pm_', 'max:80'],
            'titular' => ['required', 'string', 'min:3', 'max:150'],
            'predeterminado' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['payment_method' => 'tarjeta', 'titular' => 'titular de la tarjeta'];
    }
}
