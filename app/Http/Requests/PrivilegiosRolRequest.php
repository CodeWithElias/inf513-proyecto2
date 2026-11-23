<?php

namespace App\Http\Requests;

class PrivilegiosRolRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
    }

    public function rules(): array
    {
        return [
            'privilegios' => ['present', 'array'],
            'privilegios.*' => ['integer', 'distinct', 'exists:privilegio,id_privilegio'],
        ];
    }
}
