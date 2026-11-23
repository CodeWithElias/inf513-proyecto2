<?php

namespace App\Http\Requests;

class VisitaRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['ruta' => ['required', 'string', 'max:255', 'regex:/^\//']];
    }
}
