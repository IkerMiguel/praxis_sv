<?php

namespace App\Http\Requests;

use App\Enums\Impacto;
use App\Enums\Urgencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarPrioridadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'impacto' => ['required', Rule::enum(Impacto::class)],
            'urgencia' => ['required', Rule::enum(Urgencia::class)],
            'justificacion' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
