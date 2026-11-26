<?php

namespace App\Http\Requests;

class EjecutarMonitoreoRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_plataforma' => ['required', 'integer', 'exists:plataforma,id_plataforma'],
            'id_termino' => ['nullable', 'integer', 'exists:termino_monitoreo,id_termino'],
        ];
    }

    public function attributes(): array
    {
        return ['id_termino' => 'término'];
    }
}
