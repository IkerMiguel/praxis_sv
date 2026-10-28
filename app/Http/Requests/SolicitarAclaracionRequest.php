<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarAclaracionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['informacion_requerida' => ['required', 'string', 'min:10', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'informacion_requerida.required' => 'Debe indicar explícitamente qué datos debe aportar el reportante (RN-007).',
            'informacion_requerida.min' => 'Describa con mayor detalle la información requerida (RN-007).',
        ];
    }
}
