<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CodigoPrioridad;
use App\Enums\EstadoIncidencia;
use App\Enums\EstadoSla;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventoAuditoriaResource;
use App\Http\Resources\IncidenciaResumenResource;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\User;
use App\Services\CalculadoraSla;
use App\Services\DetalleIncidenciaService;
use App\Support\ContextoRol;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ConsolaController extends Controller
{
    public function __construct(private ContextoRol $contextoRol, private CalculadoraSla $sla) {}

    /**
     * GET /api/v1/consola/incidencias (HU-10.1): filtros combinados, búsqueda y paginación.
     * El técnico (R04) solo ve las incidencias que tiene o tuvo asignadas (D09).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'estado' => ['nullable', 'string', 'max:200'],
            'sede_id' => ['nullable', 'integer'],
            'edificio_id' => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
            'prioridad' => ['nullable', Rule::enum(CodigoPrioridad::class)],
            'tecnico_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'riesgo_inmediato' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $paginado = $this->consulta($request)
            ->with(['estado', 'categoria', 'prioridad', 'tecnicoAsignado', 'sede', 'edificio', 'piso', 'espacioFisico'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $paginado->getCollection()->transform(fn (Incidencia $incidencia) => array_merge(
            (new IncidenciaResumenResource($incidencia))->resolve($request),
            [
                'tiempo_atencion' => $this->tiempoAtencion($incidencia),
                'sla' => $this->sla->evaluarResolucion($incidencia),
            ],
        ));

        return response()->json([
            'data' => $paginado->items(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
                'sin_resultados' => $paginado->total() === 0,
            ],
        ]);
    }

    /**
     * GET /api/v1/incidencias/{id}: ficha operativa completa.
     */
    public function show(Request $request, Incidencia $incidencia, DetalleIncidenciaService $detalle): JsonResponse
    {
        $this->exigirVisibilidadOperativa($incidencia, $request->user());

        return response()->json(['data' => $detalle->paraOperador($incidencia)]);
    }

    /**
     * GET /api/v1/incidencias/{id}/historial (HU-10.2). El reportante solo ve los avances públicos.
     */
    public function historial(Request $request, Incidencia $incidencia, DetalleIncidenciaService $detalle): JsonResponse
    {
        $request->validate(['categoria' => ['nullable', Rule::in(['ESTADO', 'ACTIVIDAD', 'FORENSE', 'COMUNICACION'])]]);
        $usuario = $request->user();

        if ($this->contextoRol->es(Rol::Reportante) || ($this->contextoRol->es(Rol::Tecnico) && ! $this->tecnicoVinculado($incidencia, $usuario))) {
            $visible = $incidencia->esCreadaPor($usuario) || $incidencia->seguidores()->where('users.id', $usuario->id)->exists();

            abort_unless($visible, 404);

            return response()->json(['alcance' => 'PUBLICO', 'data' => EventoAuditoriaResource::collection($detalle->historialPublico($incidencia))]);
        }

        $eventos = EventoAuditoria::query()
            ->where('incidencia_id', $incidencia->id)
            ->when($request->filled('categoria'), fn (Builder $query) => $query->where('categoria', $request->input('categoria')))
            ->with('usuario:id,name')
            ->orderBy('fecha_hora')
            ->get();

        return response()->json(['alcance' => 'COMPLETO', 'data' => EventoAuditoriaResource::collection($eventos)]);
    }

    /**
     * GET /api/v1/consola/plazos (HU-10.3): semáforo SLA de incidencias en curso.
     */
    public function plazos(Request $request): JsonResponse
    {
        $request->validate(['estado_sla' => ['nullable', Rule::in([EstadoSla::EnPlazo->value, EstadoSla::ProximaAVencer->value, EstadoSla::Vencida->value])]]);

        $filas = Incidencia::query()
            ->whereNotIn('estado_codigo', EstadoIncidencia::valores([EstadoIncidencia::Cerrada, EstadoIncidencia::NoProcedente, EstadoIncidencia::Cancelada]))
            ->whereNotNull('fecha_limite_resolucion')
            ->with(['estado', 'prioridad', 'categoria', 'tecnicoAsignado'])
            ->get()
            ->map(fn (Incidencia $incidencia) => array_merge(
                (new IncidenciaResumenResource($incidencia))->resolve($request),
                ['sla' => $this->sla->evaluarResolucion($incidencia)],
            ));

        $orden = [EstadoSla::Vencida->value => 0, EstadoSla::ProximaAVencer->value => 1, EstadoSla::EnPlazo->value => 2];
        $resumen = [
            'en_plazo' => $filas->where('sla.estado', EstadoSla::EnPlazo->value)->count(),
            'proximas_a_vencer' => $filas->where('sla.estado', EstadoSla::ProximaAVencer->value)->count(),
            'vencidas' => $filas->where('sla.estado', EstadoSla::Vencida->value)->count(),
        ];

        return response()->json([
            'resumen' => $resumen,
            'data' => $filas
                ->when($request->filled('estado_sla'), fn ($coleccion) => $coleccion->where('sla.estado', $request->input('estado_sla')))
                ->sortBy([fn ($a, $b) => ($orden[$a['sla']['estado']] ?? 3) <=> ($orden[$b['sla']['estado']] ?? 3), fn ($a, $b) => ($b['sla']['porcentaje_consumido'] ?? 0) <=> ($a['sla']['porcentaje_consumido'] ?? 0)])
                ->values(),
        ]);
    }

    /**
     * @return Builder<Incidencia>
     */
    private function consulta(Request $request): Builder
    {
        $usuario = $request->user();
        $estados = collect(explode(',', (string) $request->input('estado')))
            ->map(fn (string $codigo) => EstadoIncidencia::tryFrom(strtoupper(trim($codigo))))
            ->filter()
            ->values()
            ->all();
        $texto = mb_strtolower(trim((string) $request->input('q')));

        return Incidencia::query()
            ->when($this->contextoRol->es(Rol::Tecnico), fn (Builder $query) => $query->where(fn (Builder $asignadas) => $asignadas
                ->where('tecnico_asignado_id', $usuario->id)
                ->orWhereHas('asignaciones', fn (Builder $historial) => $historial->where('tecnico_asignado_id', $usuario->id))))
            ->when($estados !== [], fn (Builder $query) => $query->enEstados($estados))
            ->when($request->filled('sede_id'), fn (Builder $query) => $query->where('sede_id', $request->integer('sede_id')))
            ->when($request->filled('edificio_id'), fn (Builder $query) => $query->where('edificio_id', $request->integer('edificio_id')))
            ->when($request->filled('categoria_id'), fn (Builder $query) => $query->where('categoria_id', $request->integer('categoria_id')))
            ->when($request->filled('prioridad'), fn (Builder $query) => $query->whereHas('prioridad', fn (Builder $prioridad) => $prioridad->where('codigo', $request->input('prioridad'))))
            ->when($request->filled('tecnico_id'), fn (Builder $query) => $query->where('tecnico_asignado_id', $request->integer('tecnico_id')))
            ->when($request->has('riesgo_inmediato'), fn (Builder $query) => $query->where('es_riesgo_inmediato', $request->boolean('riesgo_inmediato')))
            ->when($request->filled('fecha_desde'), fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse($request->input('fecha_desde'))->startOfDay()))
            ->when($request->filled('fecha_hasta'), fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse($request->input('fecha_hasta'))->endOfDay()))
            ->when($texto !== '', fn (Builder $query) => $query->where(fn (Builder $busqueda) => $busqueda
                ->whereRaw('LOWER(codigo_ticket) LIKE ?', ["%{$texto}%"])
                ->orWhereRaw('LOWER(resumen) LIKE ?', ["%{$texto}%"])));
    }

    /**
     * Tiempo transcurrido desde el reporte (en curso) o tiempo neto entre radicación y cierre (E15).
     *
     * @return array{horas: float, en_curso: bool, texto: string}
     */
    private function tiempoAtencion(Incidencia $incidencia): array
    {
        $fin = $incidencia->fecha_cierre ?? now();
        $horas = round($incidencia->created_at->diffInMinutes($fin) / 60, 1);

        return ['horas' => $horas, 'en_curso' => $incidencia->fecha_cierre === null, 'texto' => "{$horas} horas"];
    }

    private function exigirVisibilidadOperativa(Incidencia $incidencia, User $usuario): void
    {
        if ($this->contextoRol->es(Rol::Tecnico) && ! $this->tecnicoVinculado($incidencia, $usuario)) {
            abort(404);
        }
    }

    private function tecnicoVinculado(Incidencia $incidencia, User $usuario): bool
    {
        return (int) $incidencia->tecnico_asignado_id === (int) $usuario->id
            || $incidencia->asignaciones()->where('tecnico_asignado_id', $usuario->id)->exists();
    }
}
