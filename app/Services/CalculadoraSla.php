<?php

namespace App\Services;

use App\Enums\EstadoSla;
use App\Models\Feriado;
use App\Models\Incidencia;
use App\Support\JornadaHabil;
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
        return $this->jornadaHabil()->sumarHoras($inicio, $horas);
    }

    /**
     * Minutos hábiles transcurridos entre dos instantes.
     */
    public function minutosHabilesEntre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        return $this->jornadaHabil()->minutosEntre($desde, $hasta);
    }

    public function sumarDiasHabiles(CarbonInterface $inicio, int $dias): CarbonImmutable
    {
        return $this->jornadaHabil()->sumarDias($inicio, $dias);
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

        $estado = EstadoSla::segunConsumo($porcentaje, $umbral);

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

    /**
     * Jornada hábil vigente según los parámetros, la zona operativa y los feriados registrados.
     */
    private function jornadaHabil(): JornadaHabil
    {
        return new JornadaHabil(
            $this->parametros->entero(Parametro::JORNADA_HORA_INICIO),
            $this->parametros->entero(Parametro::JORNADA_HORA_FIN),
            $this->feriados(),
            (string) config('praxis.zona_horaria'),
        );
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
