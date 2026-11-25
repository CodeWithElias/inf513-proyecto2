<?php

namespace App\Http\Requests;

use App\Models\PlanPago;
use Illuminate\Validation\Rule;

class PlanPagoRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'id_proforma' => ['required', 'integer'],
            'nro_cuotas' => ['required', 'integer', 'between:2,24'],
            'frecuencia' => ['required', 'string', Rule::in(PlanPago::FRECUENCIAS)],
            'fecha_inicio' => ['nullable', 'date'],
        ];
    }
}

