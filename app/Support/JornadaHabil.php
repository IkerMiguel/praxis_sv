<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Aritmética de tiempo hábil sin dependencias del framework: jornada diaria [horaInicio, horaFin),
 * lunes a viernes y sin feriados, en la zona horaria operativa (RN-019).
 */
final class JornadaHabil
{
    /**
     * Tope de días recorridos, para no iterar sin fin con una configuración inválida.
     */
    private const MAXIMO_DIAS = 3660;

    /**
     * @param  list<string>  $feriados  Fechas Y-m-d no laborables.
     */
    public function __construct(
        private int $horaInicio,
        private int $horaFin,
        private array $feriados,
        private string $zonaHoraria,
    ) {}

    /**
     * Fecha (UTC) resultante de sumar horas hábiles a un instante.
     */
    public function sumarHoras(CarbonInterface $inicio, float $horas): CarbonImmutable
    {
        $restante = (int) round($horas * 60);
        $cursor = $this->aZonaLocal($inicio);

        for ($iteraciones = 0; $iteraciones < self::MAXIMO_DIAS; $iteraciones++) {
            [$inicioJornada, $finJornada] = $this->jornada($cursor);

            if (! $this->esDiaHabil($cursor) || $cursor->greaterThanOrEqualTo($finJornada)) {
                $cursor = $this->siguienteInicioJornada($cursor);

                continue;
            }

            if ($cursor->lessThan($inicioJornada)) {
                $cursor = $inicioJornada;
            }

            $disponible = (int) $cursor->diffInMinutes($finJornada);

            if ($restante <= $disponible) {
                return $cursor->addMinutes($restante)->utc();
            }

            $restante -= $disponible;
            $cursor = $this->siguienteInicioJornada($cursor);
        }

        return $cursor->utc();
    }

    /**
     * Fecha (UTC) resultante de sumar días hábiles completos (días × horas de la jornada).
     */
    public function sumarDias(CarbonInterface $inicio, int $dias): CarbonImmutable
    {
        return $this->sumarHoras($inicio, $dias * ($this->horaFin - $this->horaInicio));
    }

    /**
     * Minutos hábiles transcurridos entre dos instantes.
     */
    public function minutosEntre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        $cursor = $this->aZonaLocal($desde);
        $fin = $this->aZonaLocal($hasta);

        if ($fin->lessThanOrEqualTo($cursor)) {
            return 0;
        }

        $total = 0;

        for ($iteraciones = 0; $iteraciones < self::MAXIMO_DIAS && $cursor->lessThan($fin); $iteraciones++) {
            [$inicioJornada, $finJornada] = $this->jornada($cursor);

            if ($this->esDiaHabil($cursor)) {
                $tramoInicio = $cursor->max($inicioJornada);
                $tramoFin = $fin->min($finJornada);

                if ($tramoFin->greaterThan($tramoInicio)) {
                    $total += (int) $tramoInicio->diffInMinutes($tramoFin);
                }
            }

            $cursor = $this->siguienteInicioJornada($cursor);
        }

        return $total;
    }

    public function esDiaHabil(CarbonInterface $dia): bool
    {
        $local = $this->aZonaLocal($dia);

        return ! $local->isWeekend() && ! in_array($local->toDateString(), $this->feriados, true);
    }

    private function aZonaLocal(CarbonInterface $fecha): CarbonImmutable
    {
        return CarbonImmutable::instance($fecha)->setTimezone($this->zonaHoraria);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function jornada(CarbonImmutable $dia): array
    {
        return [$dia->setTime($this->horaInicio, 0), $dia->setTime($this->horaFin, 0)];
    }

    private function siguienteInicioJornada(CarbonImmutable $dia): CarbonImmutable
    {
        return $dia->addDay()->setTime($this->horaInicio, 0);
    }
}
