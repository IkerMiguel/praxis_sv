<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoSolicitudRecursos;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmarRecursosRequest;
use App\Http\Requests\DictamenRequest;
use App\Http\Requests\SeguimientoRecursosRequest;
use App\Http\Requests\SolicitarRecursosRequest;
use App\Models\Incidencia;
use App\Models\SolicitudRecursos;
use App\Services\RecursosService;
use Illuminate\Http\JsonResponse;

class RecursosController extends Controller
{
    public function __construct(private RecursosService $recursos) {}

    /**
     * POST /api/v1/incidencias/{id}/solicitudes-recursos (HU-8.1): E07 -> E08 o E09.
     */
    public function solicitar(SolicitarRecursosRequest $request, Incidencia $incidencia): JsonResponse
    {
        $solicitud = $this->recursos->solicitar($incidencia, $request->user(), $request->validated());

        return response()->json([
            'mensaje' => $solicitud->es_emergencia ? 'Necesidad registrada bajo emergencia autorizada.' : 'Solicitud de recursos registrada.',
            'data' => $this->solicitud($solicitud->load('detalles'), $incidencia),
        ], 201);
    }

    /**
     * GET /api/v1/autorizaciones/pendientes (bandeja R05).
     */
    public function pendientes(): JsonResponse
    {
        $solicitudes = SolicitudRecursos::query()
            ->where('estado_solicitud', EstadoSolicitudRecursos::PendienteAutorizacion->value)
            ->with(['detalles', 'solicitante:id,name', 'incidencia:id,codigo_ticket,resumen,estado_codigo,prioridad_id', 'incidencia.prioridad'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $solicitudes->map(fn (SolicitudRecursos $solicitud) => $this->solicitud($solicitud, $solicitud->incidencia))->values()]);
    }

    /**
     * POST /api/v1/solicitudes-recursos/{id}/dictamen (HU-8.2, RN-028).
     */
    public function dictamen(DictamenRequest $request, int $id): JsonResponse
    {
        $solicitud = SolicitudRecursos::query()->with('incidencia')->findOrFail($id);
        $this->recursos->dictaminar($solicitud, $request->user(), $request->validated());

        return response()->json([
            'mensaje' => 'Dictamen registrado.',
            'data' => $this->solicitud($solicitud->refresh()->load('detalles'), $solicitud->incidencia->refresh()),
        ]);
    }

    /**
     * PATCH /api/v1/incidencias/{id}/confirmar-recursos (HU-8.3): E09 -> E10.
     */
    public function confirmarRecursos(ConfirmarRecursosRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->recursos->confirmarRecursos($incidencia, $request->user(), $request->validated());

        return response()->json([
            'mensaje' => 'Recursos disponibles. Intervención programada.',
            'data' => ['id' => $incidencia->id, 'estado' => ['codigo' => $incidencia->estado_codigo->value, 'nombre' => $incidencia->estado_codigo->nombre()]],
        ]);
    }

    /**
     * POST /api/v1/solicitudes-recursos/{id}/seguimientos (HU-8.3 escenario 2, RN-030).
     */
    public function seguimiento(SeguimientoRecursosRequest $request, int $id): JsonResponse
    {
        $solicitud = SolicitudRecursos::query()->with('incidencia')->findOrFail($id);
        $seguimiento = $this->recursos->registrarSeguimiento($solicitud, $request->user(), $request->string('comentario')->toString());

        return response()->json(['mensaje' => 'Seguimiento registrado.', 'data' => $seguimiento], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitud(SolicitudRecursos $solicitud, Incidencia $incidencia): array
    {
        return [
            'id' => $solicitud->id,
            'estado_solicitud' => $solicitud->estado_solicitud->value,
            'decision_requerida' => $solicitud->decision_requerida,
            'requiere_autorizacion' => $solicitud->requiere_autorizacion,
            'es_emergencia' => $solicitud->es_emergencia,
            'resultado_dictamen' => $solicitud->resultado_dictamen,
            'justificacion_dictamen' => $solicitud->justificacion_dictamen,
            'costo_total_estimado' => $solicitud->costoTotalEstimado(),
            'detalles' => $solicitud->detalles,
            'solicitante' => $solicitud->relationLoaded('solicitante') ? $solicitud->solicitante?->only(['id', 'name']) : null,
            'fecha_solicitud' => $solicitud->created_at?->toIso8601String(),
            'incidencia' => [
                'id' => $incidencia->id,
                'codigo_ticket' => $incidencia->codigo_ticket,
                'resumen' => $incidencia->resumen,
                'estado' => ['codigo' => $incidencia->estado_codigo->value, 'nombre' => $incidencia->estado_codigo->nombre()],
            ],
        ];
    }
}
