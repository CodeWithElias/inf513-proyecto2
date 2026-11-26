<?php

namespace App\Http\Requests;

/** El cliente registra su empresa y queda como su representante (mismas reglas que EmpresaRequest + CI). */
class MiEmpresaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'min:2', 'max:200'],
            'nit' => ['required', 'string', 'min:4', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'direccion_facturacion' => ['nullable', 'string', 'max:255'],
            'email_facturacion' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\- ]{6,30}$/'],
            'ci' => ['required', 'regex:/^[0-9]{4,12}([ \-][0-9A-Za-z]{1,4})?$/', 'unique:representante,ci'],
            'cargo' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'ci.regex' => 'El CI debe tener de 4 a 12 dígitos y, opcionalmente, una extensión (ej. 7654321 SC).',
            'ci.unique' => 'Ese CI ya está registrado como representante de otra empresa.',
            'nit.regex' => 'El NIT solo puede tener letras, números y guiones.',
            'telefono.regex' => 'El teléfono solo puede tener números, espacios, + y - (de 6 a 30 caracteres).',
        ];
    }
}
