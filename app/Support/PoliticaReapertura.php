<?php

namespace App\Support;

use App\Enums\EstadoIncidencia;
use Carbon\CarbonInterface;

/**
 * Decide si una incidencia cerrada puede reabrirse: solo desde E15, nunca si fue unificada por
 * duplicidad, dentro de la ventana de garantía y con cupo de reaperturas (HU-4.5).
 */
final class PoliticaReapertura
{
    /**
     * @param  CarbonInterface|null  $fechaLimite  Fin de la ventana de garantía (cierre + días hábiles), si hubo cierre.
     * @return array{permitido: bool, motivo: string|null, fecha_limite: string|null, reaperturas_restantes: int}
     */
    public static function evaluar(
        EstadoIncidencia $estado,
        ?CarbonInterface $fechaCierre,
        ?string $resultadoCierre,
        int $numeroReaperturas,
        int $maximoReaperturas,
        ?CarbonInterface $fechaLimite,
        CarbonInterface $ahora,
    ): array {
        $restantes = max(0, $maximoReaperturas - $numeroReaperturas);

        if ($estado !== EstadoIncidencia::Cerrada || $fechaCierre === null) {
            return ['permitido' => false, 'motivo' => 'Solo se pueden reabrir incidencias cerradas.', 'fecha_limite' => null, 'reaperturas_restantes' => $restantes];
        }

        if ($resultadoCierre === ResultadoCierre::VINCULADA_POR_DUPLICIDAD) {
            return ['permitido' => false, 'motivo' => 'La solicitud fue unificada con la incidencia principal; consulte su avance.', 'fecha_limite' => null, 'reaperturas_restantes' => 0];
        }

        $limite = $fechaLimite?->toIso8601String();

        if ($fechaLimite !== null && $ahora->greaterThan($fechaLimite)) {
            return ['permitido' => false, 'motivo' => 'La ventana de garantía para reabrir la solicitud ya expiró.', 'fecha_limite' => $limite, 'reaperturas_restantes' => $restantes];
        }

        if ($restantes === 0) {
            return ['permitido' => false, 'motivo' => "La solicitud alcanzó el máximo de {$maximoReaperturas} reaperturas permitidas.", 'fecha_limite' => $limite, 'reaperturas_restantes' => 0];
        }

        return ['permitido' => true, 'motivo' => null, 'fecha_limite' => $limite, 'reaperturas_restantes' => $restantes];
    }
}
