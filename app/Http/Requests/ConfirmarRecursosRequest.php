<?php

namespace App\Http\Requests;

class ConfirmarRecursosRequest extends ProgramarIntervencionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'todos_disponibles' => ['sometimes', 'boolean'],
            'detalles_disponibles' => ['sometimes', 'array'],
            'detalles_disponibles.*' => ['integer'],
        ];
    }
}
