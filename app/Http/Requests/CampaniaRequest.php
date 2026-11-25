<?php

namespace App\Http\Requests;

use App\Models\Campania;
use Illuminate\Validation\Rule;

class CampaniaRequest extends ApiRequest
{
    public function rules(): array
    {
        $esCreacion = $this->isMethod('POST');

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'id_proforma' => $esCreacion ? ['required', 'integer'] : ['nullable', 'integer'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['nullable', 'string', Rule::in(Campania::ESTADOS)],
        ];
    }
}

