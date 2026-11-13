<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarIntervencionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actividades_realizadas' => ['required', 'string', 'min:10', 'max:4000'],
            'tiempo_invertido_minutos' => ['required', 'integer', 'min:1', 'max:100000'],
            'dificultades_observaciones' => ['nullable', 'string', 'max:4000'],
            'materiales' => ['sometimes', 'array'],
            'materiales.*.nombre' => ['required', 'string', 'max:150'],
            'materiales.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'materiales.*.unidad' => ['required', 'string', 'max:20'],
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
            'actividades_realizadas.required' => 'Debe documentar las actividades ejecutadas (RN-032).',
            'tiempo_invertido_minutos.required' => 'Debe registrar el tiempo empleado en la intervención (RN-032).',
        ];
    }
}
