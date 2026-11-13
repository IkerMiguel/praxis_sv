<?php

namespace App\Http\Resources;

use App\Models\Incidencia;

/**
 * Representación de la ubicación jerárquica de una incidencia (conserva nodos inactivos, HU-11.1).
 */
final class UbicacionIncidencia
{
    /**
     * @return array<string, mixed>
     */
    public static function de(Incidencia $incidencia): array
    {
        return [
            'sede' => $incidencia->sede?->only(['id', 'codigo', 'nombre']),
            'edificio' => $incidencia->edificio?->only(['id', 'codigo', 'nombre']),
            'piso' => $incidencia->piso?->only(['id', 'codigo', 'nombre']),
            'espacio' => $incidencia->espacioFisico?->only(['id', 'codigo', 'nombre']),
            'texto' => collect([
                $incidencia->sede?->nombre,
                $incidencia->edificio?->nombre,
                $incidencia->piso?->nombre,
                $incidencia->espacioFisico?->nombre,
            ])->filter()->implode(' > '),
        ];
    }
}
