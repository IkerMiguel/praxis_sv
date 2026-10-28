<?php

namespace App\Services;

use App\Enums\EstadoSla;
use App\Models\Feriado;
use App\Models\Incidencia;
use App\Support\Parametro;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Cálculo de plazos en horas hábiles (lunes a viernes, jornada parametrizada, sin feriados) (HU-10.3, RN-019).
 */
class CalculadoraSla
{
    public const CACHE_FERIADOS = 'praxis:feriados';

    public function __construct(private ParametrosService $parametros) {}

    /**
     * Fecha resultante de sumar horas hábiles a un instante.
     */
    public function sumarHorasHabiles(CarbonInterface $inicio, float $horas): CarbonImmutable
    {
        $restante = (int) round($horas * 60);
        $cursor = $this->aZonaLocal($inicio);

        for ($iteraciones = 0; $iteraciones < 3660; $iteraciones++) {
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
     * Minutos hábiles transcurridos entre dos instantes.
     */
    public function minutosHabilesEntre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        $cursor = $this->aZonaLocal($desde);
        $fin = $this->aZonaLocal($hasta);

        if ($fin->lessThanOrEqualTo($cursor)) {
            return 0;
        }

        $total = 0;

        for ($iteraciones = 0; $iteraciones < 3660 && $cursor->lessThan($fin); $iteraciones++) {
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

    public function sumarDiasHabiles(CarbonInterface $inicio, int $dias): CarbonImmutable
    {
        $horasPorDia = $this->parametros->entero(Parametro::JORNADA_HORA_FIN) - $this->parametros->entero(Parametro::JORNADA_HORA_INICIO);

        return $this->sumarHorasHabiles($inicio, $dias * $horasPorDia);
    }

    /**
     * Fija las metas de respuesta y resolución de una incidencia según su prioridad (RN-019).
     */
    public function fijarMetas(Incidencia $incidencia): void
    {
        $prioridad = $incidencia->prioridad;

        if ($prioridad === null) {
            return;
        }

        $base = CarbonImmutable::parse($incidencia->created_at ?? now());
        $incidencia->fecha_limite_respuesta = $this->sumarHorasHabiles($base, $prioridad->horas_primera_respuesta);
        $incidencia->fecha_limite_resolucion = $this->sumarHorasHabiles($base, $prioridad->horas_resolucion);
    }

    /**
     * Proyección semafórica del plazo de resolución (verde < umbral, amarillo entre umbral y 100 %, rojo > 100 %).
     *
     * @return array{estado: string, color: string, porcentaje_consumido: float|null, minutos_restantes: int|null, horas_retraso: float|null, fecha_limite: string|null}
     */
    public function evaluarResolucion(Incidencia $incidencia, ?CarbonInterface $ahora = null): array
    {
        $limite = $incidencia->fecha_limite_resolucion;

        if ($limite === null) {
            return $this->proyeccion(EstadoSla::SinPlazo, null, null, null, null);
        }

        if ($incidencia->sla_resolucion_cumplido !== null) {
            $estado = $incidencia->sla_resolucion_cumplido ? EstadoSla::Cumplida : EstadoSla::Incumplida;

            return $this->proyeccion($estado, null, null, null, $limite);
        }

        $ahora ??= now();
        $total = max(1, $this->minutosHabilesEntre($incidencia->created_at, $limite));
        $consumido = $this->minutosHabilesEntre($incidencia->created_at, $ahora);
        $porcentaje = round($consumido * 100 / $total, 1);
        $umbral = $this->parametros->entero(Parametro::UMBRAL_ALERTA_SLA_PORCENTAJE);

        $estado = match (true) {
            $porcentaje > 100 => EstadoSla::Vencida,
            $porcentaje >= $umbral => EstadoSla::ProximaAVencer,
            default => EstadoSla::EnPlazo,
        };

        $restantes = $total - $consumido;

        return $this->proyeccion(
            $estado,
            $porcentaje,
            max(0, $restantes),
            $restantes < 0 ? round(abs($restantes) / 60, 1) : null,
            $limite,
        );
    }

    /**
     * Congela el plazo al cerrar la incidencia (RN-039).
     */
    public function congelar(Incidencia $incidencia, CarbonInterface $fechaCierre): void
    {
        if ($incidencia->fecha_limite_resolucion !== null && $incidencia->sla_resolucion_cumplido === null) {
            $incidencia->sla_resolucion_cumplido = $fechaCierre->lessThanOrEqualTo($incidencia->fecha_limite_resolucion);
        }
    }

    public function registrarPrimeraRespuesta(Incidencia $incidencia, CarbonInterface $fecha): void
    {
        if ($incidencia->fecha_primera_respuesta !== null) {
            return;
        }

        $incidencia->fecha_primera_respuesta = $fecha;

        if ($incidencia->fecha_limite_respuesta !== null) {
            $incidencia->sla_respuesta_cumplido = $fecha->lessThanOrEqualTo($incidencia->fecha_limite_respuesta);
        }
    }

    public function invalidarFeriados(): void
    {
        Cache::forget(self::CACHE_FERIADOS);
    }

    /**
     * @return array{estado: string, color: string, porcentaje_consumido: float|null, minutos_restantes: int|null, horas_retraso: float|null, fecha_limite: string|null}
     */
    private function proyeccion(EstadoSla $estado, ?float $porcentaje, ?int $minutosRestantes, ?float $horasRetraso, ?CarbonInterface $limite): array
    {
        return [
            'estado' => $estado->value,
            'color' => $estado->color(),
            'porcentaje_consumido' => $porcentaje,
            'minutos_restantes' => $minutosRestantes,
            'horas_retraso' => $horasRetraso,
            'fecha_limite' => $limite?->toIso8601String(),
        ];
    }

    private function aZonaLocal(CarbonInterface $fecha): CarbonImmutable
    {
        return CarbonImmutable::instance($fecha)->setTimezone(config('praxis.zona_horaria'));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function jornada(CarbonImmutable $dia): array
    {
        $inicio = $this->parametros->entero(Parametro::JORNADA_HORA_INICIO);
        $fin = $this->parametros->entero(Parametro::JORNADA_HORA_FIN);

        return [$dia->setTime($inicio, 0), $dia->setTime($fin, 0)];
    }

    private function siguienteInicioJornada(CarbonImmutable $dia): CarbonImmutable
    {
        return $dia->addDay()->setTime($this->parametros->entero(Parametro::JORNADA_HORA_INICIO), 0);
    }

    private function esDiaHabil(CarbonImmutable $dia): bool
    {
        return ! $dia->isWeekend() && ! in_array($dia->toDateString(), $this->feriados(), true);
    }

    /**
     * @return list<string>
     */
    private function feriados(): array
    {
        return Cache::rememberForever(
            self::CACHE_FERIADOS,
            fn (): array => Feriado::query()->pluck('fecha')->map(fn ($fecha) => substr((string) $fecha, 0, 10))->all(),
        );
    }
}
