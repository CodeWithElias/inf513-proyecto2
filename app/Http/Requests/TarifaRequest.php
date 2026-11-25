<?php

namespace App\Http\Requests;

class TarifaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_servicio' => ['required', 'integer'],
            'id_unidad' => ['nullable', 'integer'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'unidad_cobro' => ['nullable', 'string', 'max:50'],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'duracion_seg' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ];
    }
}

