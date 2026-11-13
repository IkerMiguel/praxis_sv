<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DictamenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::in(['APROBADA', 'RECHAZADA'])],
            'justificacion' => ['nullable', 'required_if:resultado,RECHAZADA', 'string', 'min:10', 'max:1000'],
            'recursos_disponibles' => ['nullable', 'boolean'],
            'accion_rechazo' => ['nullable', 'required_if:resultado,RECHAZADA', Rule::in(['DEVOLVER_DIAGNOSTICO', 'SUSPENDER', 'CANCELAR'])],
            'condiciones_reanudacion' => ['nullable', 'required_if:accion_rechazo,SUSPENDER', 'string', 'min:10', 'max:1000'],
            'fecha_programada' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['nullable', 'required_with:fecha_programada', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'required_with:fecha_programada', 'date_format:H:i', 'after:hora_inicio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['justificacion.required_if' => 'Debe documentar el motivo de la decisión (RN-028).'];
    }
}
