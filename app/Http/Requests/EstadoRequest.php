<?php

namespace App\Http\Requests;

/** PATCH .../estado  { "activo": true|false }  (baja lógica o reactivación). */
class EstadoRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        // no convertir booleanos
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
