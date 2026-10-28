<?php

namespace App\Support;

use App\Enums\CodigoPrioridad;
use App\Enums\Impacto;
use App\Enums\Urgencia;

/**
 * Matriz institucional Impacto × Urgencia con la que se siembra la prioridad (RN-019, HU-5.3).
 * El administrador puede recalibrarla después desde /admin/parametros (HU-11.3); esta es la
 * configuración inicial.
 */
final class MatrizItil
{
    /**
     * impacto => [urgencia => prioridad].
     *
     * @var array<string, array<string, string>>
     */
    public const POR_DEFECTO = [
        'ALTO' => ['ALTA' => 'CRITICA', 'MEDIA' => 'ALTA', 'BAJA' => 'MEDIA'],
        'MEDIO' => ['ALTA' => 'ALTA', 'MEDIA' => 'BAJA', 'BAJA' => 'BAJA'],
        'BAJO' => ['ALTA' => 'MEDIA', 'MEDIA' => 'BAJA', 'BAJA' => 'BAJA'],
    ];

    public static function porDefecto(Impacto $impacto, Urgencia $urgencia): CodigoPrioridad
    {
        return CodigoPrioridad::from(self::POR_DEFECTO[$impacto->value][$urgencia->value]);
    }
}
