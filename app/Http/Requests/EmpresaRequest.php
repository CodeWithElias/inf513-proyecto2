<?php

namespace App\Http\Requests;

class EmpresaRequest extends ApiRequest
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
        ];
    }
}

