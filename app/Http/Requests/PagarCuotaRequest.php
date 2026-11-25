<?php

namespace App\Http\Requests;

class PagarCuotaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_metodo' => ['required', 'integer'],
        ];
    }
}

