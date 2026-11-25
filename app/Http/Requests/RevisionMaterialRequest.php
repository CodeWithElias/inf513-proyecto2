<?php

namespace App\Http\Requests;

use App\Models\RevisionMaterial;
use Illuminate\Validation\Rule;

class RevisionMaterialRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'resultado' => ['required', 'string', Rule::in(RevisionMaterial::RESULTADOS)],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
