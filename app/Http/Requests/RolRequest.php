<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RolRequest extends ApiRequest
{
    public function rules(): array
    {
        $id = $this->route('rol')?->id_rol;

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:100', Rule::unique('rol', 'nombre')->ignore($id, 'id_rol')],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
