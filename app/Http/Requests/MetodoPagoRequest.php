<?php

namespace App\Http\Requests;

use App\Models\MetodoPago;
use Illuminate\Validation\Rule;

class MetodoPagoRequest extends ApiRequest
{
    public function rules(): array
    {
        $esCreacion = $this->isMethod('POST');

        if (! $esCreacion) {
            return [
                'titular' => ['required', 'string', 'min:3', 'max:150'],
                'marca' => ['nullable', 'string', 'max:40'],
                'mes_exp' => ['nullable', 'integer', 'between:1,12'],
                'anio_exp' => ['nullable', 'integer', 'between:2024,2099'],
                'predeterminado' => ['nullable', 'boolean'],
            ];
        }

        return [
            'tipo' => ['required', 'string', Rule::in(MetodoPago::TIPOS)],
            'titular' => ['required', 'string', 'min:3', 'max:150'],
            'marca' => ['nullable', 'string', 'max:40'],
            'numero' => ['required_if:tipo,tarjeta', 'nullable', 'string', 'min:13', 'max:25'],
            'cvv' => ['required_if:tipo,tarjeta', 'nullable', 'string', 'regex:/^\d{3,4}$/'],
            'mes_exp' => ['required_if:tipo,tarjeta', 'nullable', 'integer', 'between:1,12'],
            'anio_exp' => ['required_if:tipo,tarjeta', 'nullable', 'integer', 'between:2024,2099'],
            'predeterminado' => ['nullable', 'boolean'],
        ];
    }
}

