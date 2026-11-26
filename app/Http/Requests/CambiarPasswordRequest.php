<?php

namespace App\Http\Requests;

class CambiarPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'password_actual' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed', 'different:password_actual'],
        ];
    }

    public function attributes(): array
    {
        return ['password_actual' => 'contraseña actual', 'password' => 'nueva contraseña'];
    }

    public function messages(): array
    {
        return ['password.different' => 'La nueva contraseña debe ser distinta de la actual.'];
    }
}
