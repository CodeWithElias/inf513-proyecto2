<?php

namespace App\Http\Requests;

use App\Models\CanalDifusion;
use Illuminate\Validation\Rule;

class CanalRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(CanalDifusion::TIPOS)],
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return ['tipo.in' => 'El tipo de canal debe ser televisión, radio o web. Las redes sociales se registran en Redes institucionales.'];
    }
}
