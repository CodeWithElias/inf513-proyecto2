<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las validaciones de la API. La autorización la hace el middleware
 * "permiso" (matriz de acceso); aquí solo se validan los datos de entrada.
 * Los mensajes salen de lang/es/validation.php (en español).
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Convierte "" en null para los campos opcionales de los selects. */
    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->all())
            ->except(['password'])
            ->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)
            ->all());
    }
}
