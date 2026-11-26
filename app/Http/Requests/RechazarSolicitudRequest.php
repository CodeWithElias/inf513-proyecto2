<?php

namespace App\Http\Requests;

class RechazarSolicitudRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:300']];
    }

    public function attributes(): array
    {
        return ['motivo' => 'motivo del rechazo'];
    }
}
