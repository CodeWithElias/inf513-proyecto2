<?php

namespace App\Http\Requests;

class TerminoRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'termino' => ['required', 'string', 'min:3', 'max:200'],
            'tipo' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return ['termino.min' => 'El término debe tener al menos 3 caracteres para evitar resultados irrelevantes.'];
    }
}
