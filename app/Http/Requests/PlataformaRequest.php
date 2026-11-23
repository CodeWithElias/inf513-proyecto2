<?php

namespace App\Http\Requests;

class PlataformaRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['nombre' => ['required', 'string', 'min:2', 'max:100', 'unique:plataforma,nombre']];
    }
}
