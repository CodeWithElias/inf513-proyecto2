<?php

namespace App\Http\Requests;

class RedSocialRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:150'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'id_plataforma' => ['required', 'integer'],
            'id_usuario' => ['nullable', 'integer'],
            'id_unidad' => ['nullable', 'integer'],
        ];
    }
}
