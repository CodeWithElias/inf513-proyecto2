<?php

namespace App\Http\Requests;

class PautaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_material' => ['required', 'integer'],
            'id_espacio' => ['required', 'integer'],
            'id_detalle' => ['required', 'integer'],
        ];
    }
}
