<?php

namespace App\Http\Requests;

class CotizacionPublicaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\- ]{6,30}$/'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_tarifa' => ['nullable', 'integer'],
            'detalles.*.id_paquete' => ['nullable', 'integer'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
            'detalles.*.frecuencia' => ['nullable', 'string', 'max:50'],
            // Visitante → Cliente: si quiere una cuenta, elige su contraseña (queda pendiente de aprobación)
            'crear_cuenta' => ['nullable', 'boolean'],
            'password' => ['exclude_unless:crear_cuenta,true', 'required', 'string', 'min:6', 'max:100', 'confirmed'],
        ];
    }
}

