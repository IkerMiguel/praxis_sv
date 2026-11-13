<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarDiagnosticoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hallazgos' => ['required', 'string', 'min:10', 'max:4000'],
            'descripcion_problema' => ['required', 'string', 'min:10', 'max:4000'],
            'alcance' => ['nullable', 'string', 'max:4000'],
            'intervencion_propuesta' => ['required', 'string', 'min:10', 'max:4000'],
            'requiere_recursos' => ['sometimes', 'boolean'],
            'requiere_autorizacion' => ['sometimes', 'boolean'],
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
            'hallazgos.required' => 'Los hallazgos de la inspección son obligatorios (RN-022).',
            'descripcion_problema.required' => 'Debe describir el problema o causa raíz (RN-023).',
            'intervencion_propuesta.required' => 'La propuesta de intervención es obligatoria (RN-023).',
        ];
    }
}
