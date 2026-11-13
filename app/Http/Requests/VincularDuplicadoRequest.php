<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VincularDuplicadoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'incidencia_principal_id' => ['required', 'uuid'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }
}
