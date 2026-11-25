<?php

namespace App\Http\Requests;

class ProformaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_empresa' => ['nullable', 'integer'],
            'id_visitante' => ['nullable', 'integer'],
            'fecha_validez' => ['nullable', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_tarifa' => ['nullable', 'integer'],
            'detalles.*.id_paquete' => ['nullable', 'integer'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
            'detalles.*.frecuencia' => ['nullable', 'string', 'max:50'],
        ];
    }
}

