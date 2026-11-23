<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RepresentanteRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('representante')?->id_usuario;

        return [
            'id_usuario' => [$id ? 'nullable' : 'required', 'integer'],
            'ci' => ['required', 'regex:/^[0-9]{4,12}([ \-][0-9A-Za-z]{1,4})?$/', Rule::unique('representante', 'ci')->ignore($id, 'id_usuario')],
            'cargo' => ['nullable', 'string', 'max:100'],
            'id_empresa' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['ci.regex' => 'El CI debe tener de 4 a 12 dígitos y, opcionalmente, una extensión (ej. 7654321 SC).'];
    }
}
