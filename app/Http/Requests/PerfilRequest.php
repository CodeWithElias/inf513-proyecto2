<?php

namespace App\Http\Requests;

/** Datos que el propio usuario puede cambiar. El correo no: identifica la cuenta (también en el sistema vía correo). */
class PerfilRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nombre_completo' => ['required', 'string', 'min:3', 'max:200'],
            'telefono' => ['nullable', 'regex:/^[0-9+\- ]{6,30}$/'],
        ];
    }

    public function messages(): array
    {
        return ['telefono.regex' => 'El teléfono solo puede tener números, espacios, + y - (de 6 a 30 caracteres).'];
    }
}
