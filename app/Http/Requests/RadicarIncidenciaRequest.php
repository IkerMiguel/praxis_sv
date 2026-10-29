<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RadicarIncidenciaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'edificio_id' => ['required', 'integer', 'exists:edificios,id'],
            'piso_id' => ['required', 'integer', 'exists:pisos,id'],
            'espacio_fisico_id' => ['required', 'integer', 'exists:espacios_fisicos,id'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias_incidencia', 'id')->whereNull('categoria_padre_id')->where('activo', true)],
            'subcategoria_id' => ['nullable', 'integer', Rule::exists('categorias_incidencia', 'id')->where('categoria_padre_id', $this->integer('categoria_id'))->where('activo', true)],
            'resumen' => ['nullable', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'min:20', 'max:500'],
            'canal_radicacion' => ['nullable', Rule::in(['WEB', 'MOVIL'])],
            'es_riesgo_inmediato' => ['sometimes', 'boolean'],
            'descripcion_riesgo' => ['nullable', 'required_if_accepted:es_riesgo_inmediato', 'string', 'max:500'],
            'es_problema_diferente' => ['sometimes', 'boolean'],
            'evidencias' => ['sometimes', 'array'],
            'evidencias.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'espacio_fisico_id.required' => 'Debe especificar el aula o espacio específico (RN-003)',
            'piso_id.required' => 'Debe seleccionar el piso o nivel (RN-003)',
            'edificio_id.required' => 'Debe seleccionar el edificio o bloque (RN-003)',
            'sede_id.required' => 'Debe seleccionar la sede o campus (RN-003)',
            'descripcion.min' => 'La descripción debe tener al menos 20 caracteres.',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres.',
            'descripcion_riesgo.required_if_accepted' => 'Debe describir brevemente el peligro inminente para activar el protocolo de seguridad',
            'subcategoria_id.exists' => 'La subcategoría no pertenece a la categoría seleccionada.',
        ];
    }
}
