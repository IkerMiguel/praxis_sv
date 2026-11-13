<?php

namespace App\Http\Requests;

class CancelarSolicitudRequest extends MotivoRequest
{
    protected int $minimo = 15;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'motivo.required' => 'Debe ingresar el motivo por el cual cancela el reporte',
        ]);
    }
}
