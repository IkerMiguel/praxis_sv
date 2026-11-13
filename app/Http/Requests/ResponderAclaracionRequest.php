<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResponderAclaracionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'respuesta' => ['required', 'string', 'max:500'],
            'evidencias' => ['sometimes', 'array', 'max:4'],
            'evidencias.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'respuesta.required' => 'El texto de la aclaración es obligatorio.',
            'respuesta.max' => 'La aclaración no puede superar los 500 caracteres.',
        ];
    }
}
