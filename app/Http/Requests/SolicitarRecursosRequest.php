<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarRecursosRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision_requerida' => ['required', 'string', 'min:10', 'max:500'],
            'requiere_autorizacion' => ['required', 'boolean'],
            'es_emergencia' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.recurso_nombre' => ['required', 'string', 'max:150'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.unidad' => ['required', 'string', 'max:20'],
            'items.*.costo_estimado' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision_requerida.required' => 'Debe indicar la decisión administrativa requerida (RN-027).',
            'items.required' => 'Debe identificar al menos un recurso requerido (RN-027).',
        ];
    }
}
