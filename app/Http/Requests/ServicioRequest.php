<?php

namespace App\Http\Requests;

use App\Models\ServicioPublicitario;
use Illuminate\Validation\Rule;

class ServicioRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
            'tipo' => ['nullable', 'string', Rule::in(ServicioPublicitario::TIPOS)],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }
}

