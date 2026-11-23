<?php

namespace App\Http\Requests;

class SeguidoresRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'seguidores' => ['required', 'integer', 'min:0', 'max:999999999'],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
