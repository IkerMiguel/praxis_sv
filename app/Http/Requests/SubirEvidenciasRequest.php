<?php

namespace App\Http\Requests;

use App\Enums\EtapaEvidencia;
use App\Services\ParametrosService;
use App\Support\Parametro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubirEvidenciasRequest extends FormRequest
{
    /**
     * El tipo real se valida por magic bytes en EvidenciaService; aquí se controlan cantidad y tamaño.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $parametros = app(ParametrosService::class);
        $maximo = $parametros->entero(Parametro::MAX_EVIDENCIAS_POR_REPORTE);
        $kilobytes = $parametros->entero(Parametro::TAMANO_MAXIMO_EVIDENCIA_MB) * 1024;

        return [
            'archivos' => ['required', 'array', 'min:1', "max:{$maximo}"],
            'archivos.*' => ['required', 'file', "max:{$kilobytes}"],
            'incidencia_id' => ['nullable', 'uuid'],
            'etapa' => ['nullable', Rule::enum(EtapaEvidencia::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $parametros = app(ParametrosService::class);

        return [
            'archivos.max' => 'Se ha alcanzado el límite máximo permitido de '.$parametros->entero(Parametro::MAX_EVIDENCIAS_POR_REPORTE).' archivos por reporte',
            'archivos.*.max' => 'El archivo supera el tamaño máximo autorizado de '.$parametros->entero(Parametro::TAMANO_MAXIMO_EVIDENCIA_MB).' MB',
        ];
    }
}
