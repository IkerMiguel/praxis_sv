<?php

namespace App\Http\Requests;

class RechazarVerificacionRequest extends VerificarIntervencionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'motivo_rechazo' => ['required', 'string', 'min:10', 'max:1000'],
            'correccion_requerida' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_rechazo.required' => 'Debe registrar el motivo del rechazo (RN-035).',
            'correccion_requerida.required' => 'Debe especificar la corrección técnica requerida (RN-035).',
        ];
    }
}
