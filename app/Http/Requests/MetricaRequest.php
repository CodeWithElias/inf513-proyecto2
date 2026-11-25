<?php

namespace App\Http\Requests;

class MetricaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_programacion' => ['required', 'integer'],
            'alcance' => ['required', 'integer', 'min:0'],
            'impresiones' => ['required', 'integer', 'min:0'],
            'interacciones' => ['nullable', 'integer', 'min:0'],
            'clics' => ['nullable', 'integer', 'min:0'],
            'compartidos' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
