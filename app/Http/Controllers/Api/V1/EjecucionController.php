<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoIncidencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\DescartarPorInspeccionRequest;
use App\Http\Requests\RegistrarDiagnosticoRequest;
use App\Http\Requests\RegistrarIntervencionRequest;
use App\Http\Resources\IncidenciaResumenResource;
use App\Models\Incidencia;
use App\Services\EjecucionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EjecucionController extends Controller
{
    public function __construct(private EjecucionService $ejecucion) {}

    /**
     * GET /api/v1/tecnico/tareas: órdenes activas del técnico autenticado (/mis-tareas).
     */
    public function misTareas(Request $request): AnonymousResourceCollection
    {
        $tareas = Incidencia::query()
            ->where('tecnico_asignado_id', $request->user()->id)
            ->enEstados([
                EstadoIncidencia::PendienteInspeccion, EstadoIncidencia::EnDiagnostico, EstadoIncidencia::PendienteAutorizacion,
                EstadoIncidencia::PendienteRecursos, EstadoIncidencia::Programada, EstadoIncidencia::EnAtencion,
                EstadoIncidencia::EnCorreccion, EstadoIncidencia::Suspendida,
            ])
            ->with(['estado', 'categoria', 'prioridad', 'sede', 'edificio', 'piso', 'espacioFisico'])
            ->orderByDesc('es_riesgo_inmediato')
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 20));

        return IncidenciaResumenResource::collection($tareas);
    }

    /**
     * POST /api/v1/incidencias/{id}/iniciar-inspeccion (HU-7.1): E06 -> E07.
     */
    public function iniciarInspeccion(Request $request, Incidencia $incidencia): JsonResponse
    {
        $diagnostico = $this->ejecucion->iniciarInspeccion($incidencia, $request->user());

        return $this->respuesta($incidencia, 'Inspección iniciada.', ['diagnostico_id' => $diagnostico->id]);
    }

    /**
     * POST /api/v1/incidencias/{id}/diagnostico (HU-7.1, RF-006).
     */
    public function diagnostico(RegistrarDiagnosticoRequest $request, Incidencia $incidencia): JsonResponse
    {
        $diagnostico = $this->ejecucion->registrarDiagnostico($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Diagnóstico registrado.', ['diagnostico_id' => $diagnostico->id]);
    }

    /**
     * POST /api/v1/incidencias/{id}/descartar-por-inspeccion (HU-7.2, RN-024).
     */
    public function descartarPorInspeccion(DescartarPorInspeccionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $diagnostico = $this->ejecucion->descartarPorInspeccion($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Descarte registrado. Pendiente del visto bueno del coordinador.', ['diagnostico_id' => $diagnostico->id]);
    }

    /**
     * POST /api/v1/incidencias/{id}/iniciar-atencion (HU-7.3): E10 -> E11.
     */
    public function iniciarAtencion(Request $request, Incidencia $incidencia): JsonResponse
    {
        $actividad = $this->ejecucion->iniciarAtencion($incidencia, $request->user());

        return $this->respuesta($incidencia, 'Intervención iniciada.', ['actividad_id' => $actividad->id, 'fecha_inicio' => $actividad->fecha_inicio->toIso8601String()]);
    }

    /**
     * POST /api/v1/incidencias/{id}/registrar-intervencion (HU-7.3): E11 -> E12 (RN-032, RN-033).
     */
    public function registrarIntervencion(RegistrarIntervencionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $actividad = $this->ejecucion->registrarIntervencion($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Intervención registrada. Pendiente de verificación.', ['actividad_id' => $actividad->id]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function respuesta(Incidencia $incidencia, string $mensaje, array $extra = []): JsonResponse
    {
        return response()->json([
            'mensaje' => $mensaje,
            'data' => array_merge([
                'id' => $incidencia->id,
                'codigo_ticket' => $incidencia->codigo_ticket,
                'estado' => ['codigo' => $incidencia->estado_codigo->value, 'nombre' => $incidencia->estado_codigo->nombre()],
            ], $extra),
        ]);
    }
}
