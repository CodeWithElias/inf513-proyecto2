<?php

namespace App\Http\Requests;

use App\Models\EspacioPublicitario;
use Illuminate\Validation\Rule;

class EspacioRequest extends ApiRequest
{
    public function rules(): array
    {
        $esCreacion = $this->isMethod('POST');

        return [
            'id_canal' => $esCreacion ? ['required', 'integer'] : ['nullable', 'integer'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i', 'before:hora_fin'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'estado' => ['nullable', 'string', Rule::in(EspacioPublicitario::ESTADOS)],
        ];
    }
}
