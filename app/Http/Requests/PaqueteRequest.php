<?php

namespace App\Http\Requests;

class PaqueteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_tarifa' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}

