<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsignarTecnicoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tecnico_id' => ['required', 'integer'],
            'instrucciones' => ['nullable', 'string', 'max:1000'],
            'fecha_visita_tentativa' => ['nullable', 'date', 'after_or_equal:today'],
            'motivo_reasignacion' => ['nullable', 'string', 'min:10', 'max:500'],
        ];
    }
}
