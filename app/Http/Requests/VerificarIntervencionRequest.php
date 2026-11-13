<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerificarIntervencionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'criterios' => ['sometimes', 'array'],
            'criterios.*.criterio' => ['required', 'string', 'max:200'],
            'criterios.*.cumple' => ['required', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
