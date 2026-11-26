<?php

namespace App\Http\Requests;

use App\Models\MaterialPublicitario;
use Illuminate\Validation\Rule;

class MaterialRequest extends ApiRequest
{
    public function rules(): array
    {
        // Alta = POST /materiales. «Subir nueva versión» también es POST, pero sobre un material que ya existe
        // (POST /materiales/{material}/version): ahí la campaña es la del material y no se pide.
        $esCreacion = $this->isMethod('POST') && $this->route('material') === null;

        return [
            'id_campania' => $esCreacion ? ['required', 'integer'] : ['nullable', 'integer'],
            'tipo' => ['required', 'string', Rule::in(MaterialPublicitario::TIPOS)],
            'nombre_archivo' => ['nullable', 'string', 'max:255'],
            'url_archivo' => ['nullable', 'string', 'max:500'],
            'formato' => ['nullable', 'string', 'max:30'],
            'duracion_seg' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'tamano_bytes' => ['nullable', 'integer', 'min:0'],
            'texto_publicacion' => ['nullable', 'string', 'max:3000'],
            // Archivo subido desde la web (alternativa a url_archivo); el formato se valida según el tipo en MaterialService
            'archivo' => ['nullable', 'file', 'max:'.\App\Support\ArchivoMaterial::MAX_KB],
        ];
    }
}
