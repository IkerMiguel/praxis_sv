<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Justificación obligatoria genérica. Las subclases fijan la longitud mínima exigida por cada HU.
 */
class MotivoRequest extends FormRequest
{
    protected int $minimo = 10;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', "min:{$this->minimo}", 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio.',
            'motivo.min' => "El motivo debe tener al menos {$this->minimo} caracteres.",
        ];
    }
}
