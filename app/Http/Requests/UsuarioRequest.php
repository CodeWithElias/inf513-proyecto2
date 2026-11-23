<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UsuarioRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('usuario')?->id_usuario;

        return [
            'nombre_completo' => ['required', 'string', 'min:3', 'max:200'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuario', 'email')->ignore($id, 'id_usuario')],
            'telefono' => ['nullable', 'regex:/^[0-9+\- ]{6,30}$/'],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
            'id_rol' => ['required', 'integer'],
            'id_unidad' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['telefono.regex' => 'El teléfono solo puede tener números, espacios, + y - (de 6 a 30 caracteres).'];
    }
}
