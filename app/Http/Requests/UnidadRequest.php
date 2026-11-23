<?php

namespace App\Http\Requests;

class UnidadRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'id_unidad_padre' => ['nullable', 'integer'],
        ];
    }
}
