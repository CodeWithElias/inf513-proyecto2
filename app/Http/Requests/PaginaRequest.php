<?php

namespace App\Http\Requests;

class PaginaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:500'],
            'nombre_pagina' => ['nullable', 'string', 'max:200'],
            'id_plataforma' => ['required', 'integer', 'exists:plataforma,id_plataforma'],
        ];
    }
}
