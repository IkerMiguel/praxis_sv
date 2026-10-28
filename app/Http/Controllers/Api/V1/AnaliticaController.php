<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoIncidencia;
use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use App\Services\ParametrosService;
use App\Support\Parametro;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnaliticaController extends Controller
{
    /**
     * GET /api/v1/analitica/metricas-basicas?periodo=semana|mes|semestre|rango&desde=&hasta= (HU-10.4).
     * Agregaciones SQL con protección ante periodos vacíos (sin divisiones por cero).
     */
    public function metricasBasicas(Request $request, ParametrosService $parametros): JsonResponse
    {
        $request->validate([
            'periodo' => ['nullable', Rule::in(['semana', 'mes', 'semestre', 'rango'])],
            'desde' => ['required_if:periodo,rango', 'nullable', 'date'],
            'hasta' => ['required_if:periodo,rango', 'nullable', 'date', 'after_or_equal:desde'],
        ]);

        [$desde, $hasta] = $this->rango((string) $request->input('periodo', 'mes'), $request->input('desde'), $request->input('hasta'));

        $porEstado = Incidencia::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('estado_codigo, COUNT(*) as total')
            ->groupBy('estado_codigo')
            ->pluck('total', 'estado_codigo')
            ->map(fn ($total) => (int) $total);

        $total = (int) $porEstado->sum();

        $cerradas = Incidencia::query()
            ->where('estado_codigo', EstadoIncidencia::Cerrada->value)
            ->whereNotNull('fecha_cierre')
            ->whereBetween('fecha_cierre', [$desde, $hasta]);

        $agregadoCierre = (clone $cerradas)->selectRaw('COUNT(*) as cantidad, AVG('.$this->horasEntre('created_at', 'fecha_cierre').') as promedio')->first();
        $cantidadCerradas = (int) ($agregadoCierre->cantidad ?? 0);

        $sla = (clone $cerradas)->whereNotNull('sla_resolucion_cumplido')
            ->selectRaw('COUNT(*) as evaluadas, SUM(CASE WHEN sla_resolucion_cumplido THEN 1 ELSE 0 END) as cumplidas')
            ->first();
        $evaluadas = (int) ($sla->evaluadas ?? 0);

        $ventana = $parametros->entero(Parametro::RECURRENCIA_VENTANA_DIAS);
        $minimo = $parametros->entero(Parametro::RECURRENCIA_MINIMO_REPORTES);

        $recurrentes = Incidencia::query()
            ->join('espacios_fisicos', 'espacios_fisicos.id', '=', 'incidencias.espacio_fisico_id')
            ->join('categorias_incidencia', 'categorias_incidencia.id', '=', 'incidencias.categoria_id')
            ->where('incidencias.created_at', '>=', now()->subDays($ventana))
            ->groupBy('incidencias.espacio_fisico_id', 'espacios_fisicos.nombre', 'incidencias.categoria_id', 'categorias_incidencia.nombre')
            ->havingRaw('COUNT(*) >= ?', [$minimo])
            ->selectRaw('incidencias.espacio_fisico_id, espacios_fisicos.nombre as espacio, incidencias.categoria_id, categorias_incidencia.nombre as categoria, COUNT(*) as reportes, MAX(incidencias.created_at) as ultimo_reporte')
            ->orderByDesc('reportes')
            ->get()
            ->map(fn ($fila) => [
                'espacio_fisico_id' => (int) $fila->espacio_fisico_id,
                'espacio' => $fila->espacio,
                'categoria_id' => (int) $fila->categoria_id,
                'categoria' => $fila->categoria,
                'reportes' => (int) $fila->reportes,
                'ultimo_reporte' => $fila->ultimo_reporte,
            ]);

        $porPrioridad = Incidencia::query()
            ->leftJoin('prioridades', 'prioridades.id', '=', 'incidencias.prioridad_id')
            ->whereBetween('incidencias.created_at', [$desde, $hasta])
            ->selectRaw("COALESCE(prioridades.codigo, 'SIN_CLASIFICAR') as prioridad, COUNT(*) as total")
            ->groupBy('prioridades.codigo')
            ->pluck('total', 'prioridad')
            ->map(fn ($valor) => (int) $valor);

        return response()->json(['data' => [
            'periodo' => ['desde' => $desde->toIso8601String(), 'hasta' => $hasta->toIso8601String()],
            'sin_datos' => $total === 0 && $cantidadCerradas === 0,
            'total_incidencias' => $total,
            'por_estado' => collect(EstadoIncidencia::cases())->map(fn (EstadoIncidencia $estado) => [
                'codigo' => $estado->value,
                'nombre' => $estado->nombre(),
                'total' => $porEstado[$estado->value] ?? 0,
            ])->values(),
            'por_prioridad' => $porPrioridad,
            'mttr' => [
                'incidencias_cerradas' => $cantidadCerradas,
                'horas_promedio' => $cantidadCerradas > 0 ? round((float) $agregadoCierre->promedio, 1) : null,
                'texto' => $cantidadCerradas > 0 ? 'Tiempo promedio: '.round((float) $agregadoCierre->promedio, 1).' horas' : 'Sin información en el periodo seleccionado',
            ],
            'cumplimiento_sla' => [
                'evaluadas' => $evaluadas,
                'cumplidas' => (int) ($sla->cumplidas ?? 0),
                'porcentaje' => $evaluadas > 0 ? round(((int) $sla->cumplidas) * 100 / $evaluadas, 1) : null,
            ],
            'fallas_recurrentes' => [
                'ventana_dias' => $ventana,
                'minimo_reportes' => $minimo,
                'data' => $recurrentes,
            ],
        ]]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rango(string $periodo, ?string $desde, ?string $hasta): array
    {
        $ahora = CarbonImmutable::now();

        return match ($periodo) {
            'semana' => [$ahora->subDays(7)->startOfDay(), $ahora],
            'semestre' => [$ahora->month <= 6 ? $ahora->startOfYear() : $ahora->setDate($ahora->year, 7, 1)->startOfDay(), $ahora],
            'rango' => [CarbonImmutable::parse($desde)->startOfDay(), CarbonImmutable::parse($hasta)->endOfDay()],
            default => [$ahora->startOfMonth(), $ahora],
        };
    }

    private function horasEntre(string $inicio, string $fin): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$fin} - {$inicio})) / 3600.0",
            'sqlite' => "(julianday({$fin}) - julianday({$inicio})) * 24.0",
            default => "TIMESTAMPDIFF(SECOND, {$inicio}, {$fin}) / 3600.0",
        };
    }
}
