<?php

namespace App\Exceptions;

use App\Enums\EstadoIncidencia;

/**
 * Transición de estado no contemplada en la matriz D07 para el estado actual.
 */
class TransicionNoPermitidaException extends ReglaNegocioException
{
    public function __construct(EstadoIncidencia $origen, EstadoIncidencia $destino, ?string $mensaje = null)
    {
        parent::__construct(
            $mensaje ?? "La incidencia está en {$origen->value} ({$origen->nombre()}) y no puede pasar a {$destino->value} ({$destino->nombre()}).",
            'D07',
            409,
            ['estado_actual' => $origen->value, 'estado_destino' => $destino->value],
        );
    }
}
