<?php

namespace App\Http\Requests;

class PagarProformaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_proforma' => ['required', 'integer'],
            'id_metodo' => ['required', 'integer'],
        ];
    }
}

