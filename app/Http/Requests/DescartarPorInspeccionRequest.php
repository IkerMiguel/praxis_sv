<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DescartarPorInspeccionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hallazgos' => ['required', 'string', 'min:10', 'max:4000'],
            'justificacion' => ['required', 'string', 'min:20', 'max:4000'],
            'evidencias' => ['sometimes', 'array', 'max:10'],
            'evidencias.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hallazgos.required' => 'Debe detallar los hallazgos de la revisión (RN-022).',
            'justificacion.required' => 'Debe detallar el motivo por el cual no se confirma la incidencia (RN-024).',
        ];
    }
}
