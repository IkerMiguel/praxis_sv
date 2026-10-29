<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluarRiesgoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tiene_riesgo_inmediato' => ['required', 'boolean'],
            'nivel_riesgo' => ['required', Rule::in(['BAJO', 'MEDIO', 'ALTO', 'INMEDIATO'])],
            'acciones_adoptadas' => ['nullable', 'required_if_accepted:tiene_riesgo_inmediato', 'string', 'min:10', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'acciones_adoptadas.required_if_accepted' => 'Debe registrar las acciones preventivas o de contención adoptadas antes de activar el protocolo (RN-018).',
        ];
    }
}
