<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CierreAdministrativoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['notas' => ['nullable', 'string', 'max:1000']];
    }
}
