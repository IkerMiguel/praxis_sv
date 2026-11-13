<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuspenderIncidenciaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'causal' => ['required', 'string', 'max:60'],
            'motivo_detallado' => ['required', 'string', 'min:10', 'max:1000'],
            'condiciones_reanudacion' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_detallado.required' => 'Debe documentar la causa de la suspensión (RN-031).',
            'condiciones_reanudacion.required' => 'Debe indicar las condiciones requeridas para reanudar (D07).',
        ];
    }
}
