<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class EstadoProformaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(['aprobada', 'rechazada'])],
        ];
    }
}

