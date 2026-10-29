<?php

namespace App\Http\Requests;

use App\Enums\Impacto;
use App\Enums\Urgencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClasificarIncidenciaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'procedente' => ['required', 'boolean'],
            'motivo' => ['exclude_if:procedente,true', 'required', 'string', 'min:20', 'max:1000'],
            'impacto' => ['exclude_if:procedente,false', 'required', Rule::enum(Impacto::class)],
            'urgencia' => ['exclude_if:procedente,false', 'required', Rule::enum(Urgencia::class)],
            'justificacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe justificar la no procedencia (mínimo 20 caracteres).',
            'motivo.min' => 'La justificación de no procedencia debe tener al menos 20 caracteres.',
            'impacto.required' => 'Debe seleccionar el impacto institucional.',
            'urgencia.required' => 'Debe seleccionar la urgencia operativa.',
        ];
    }
}
