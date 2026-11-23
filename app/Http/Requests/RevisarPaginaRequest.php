<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RevisarPaginaRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['estado' => ['required', Rule::in(['confirmada', 'denunciada', 'eliminada'])]];
    }

    public function messages(): array
    {
        return ['estado.in' => 'El estado debe ser confirmada, denunciada o eliminada.'];
    }
}
