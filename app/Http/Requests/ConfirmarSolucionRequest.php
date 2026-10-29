<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarSolucionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'calificacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ];
    }
}
