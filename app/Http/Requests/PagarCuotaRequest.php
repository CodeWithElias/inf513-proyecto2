<?php

namespace App\Http\Requests;

class PagarCuotaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_metodo' => ['required', 'integer'],
            // Tras la verificación 3D Secure, el navegador reenvía el pago con el id del cobro de Stripe
            'payment_intent' => ['nullable', 'string', 'starts_with:pi_', 'max:80'],
        ];
    }
}

