<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Impacto;
use App\Enums\Urgencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarPrioridadRequest;
use App\Http\Requests\ClasificarIncidenciaRequest;
use App\Http\Requests\EvaluarRiesgoRequest;
use App\Http\Requests\SolicitarAclaracionRequest;
use App\Http\Requests\VincularDuplicadoRequest;
use App\Models\Incidencia;
use App\Services\TriajeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriajeController extends Controller
{
    public function __construct(private TriajeService $triaje) {}

    /**
     * POST /api/v1/incidencias/{id}/iniciar-revision (HU-5.1): E01 -> E03.
     */
    public function iniciarRevision(Request $request, Incidencia $incidencia): JsonResponse
    {
        $this->triaje->iniciarRevision($incidencia, $request->user());

        return $this->estado($incidencia, 'Revisión iniciada.');
    }

    /**
     * POST /api/v1/incidencias/{id}/solicitar-aclaracion (HU-5.1): E03 -> E02 con motivo obligatorio (RN-007).
     */
    public function solicitarAclaracion(SolicitarAclaracionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $aclaracion = $this->triaje->solicitarAclaracion($incidencia, $request->user(), $request->string('informacion_requerida')->toString());

        return $this->estado($incidencia, 'Aclaración solicitada al reportante.', [
            'aclaracion_id' => $aclaracion->id,
            'fecha_limite_respuesta' => $aclaracion->fecha_limite_respuesta->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/incidencias/{id}/posibles-duplicados (HU-5.2, RN-011).
     */
    public function posiblesDuplicados(Incidencia $incidencia): JsonResponse
    {
        return response()->json(['data' => $this->triaje->posiblesDuplicados($incidencia)]);
    }

    /**
     * POST /api/v1/incidencias/{id}/vincular-duplicado (HU-5.2, RF-003).
     */
    public function vincularDuplicado(VincularDuplicadoRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->triaje->vincularDuplicado($incidencia, $request->user(), $request->string('incidencia_principal_id')->toString(), $request->input('observaciones'));

        return $this->estado($incidencia, 'Incidencia vinculada como duplicada.', ['incidencia_principal_id' => $incidencia->incidencia_principal_id]);
    }

    /**
     * POST /api/v1/incidencias/{id}/descartar-duplicado (HU-5.2 escenario 3).
     */
    public function descartarDuplicado(Request $request, Incidencia $incidencia): JsonResponse
    {
        $request->validate(['observaciones' => ['nullable', 'string', 'max:500']]);
        $this->triaje->descartarDuplicado($incidencia, $request->user(), $request->input('observaciones'));

        return $this->estado($incidencia, 'Se descartó la sugerencia de duplicado.');
    }

    /**
     * POST /api/v1/incidencias/{id}/clasificar (HU-5.3): procedencia + matriz Impacto x Urgencia.
     */
    public function clasificar(ClasificarIncidenciaRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->triaje->clasificar($incidencia, $request->user(), $request->validated());

        return $this->estado($incidencia, $request->boolean('procedente') ? 'Incidencia clasificada y remitida a coordinación.' : 'Incidencia declarada no procedente.', [
            'prioridad' => $incidencia->prioridad?->only(['codigo', 'nombre']),
        ]);
    }

    /**
     * PATCH /api/v1/incidencias/{id}/prioridad: reevaluación con justificación (RN-020).
     */
    public function cambiarPrioridad(CambiarPrioridadRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->triaje->cambiarPrioridad(
            $incidencia,
            $request->user(),
            $request->enum('impacto', Impacto::class),
            $request->enum('urgencia', Urgencia::class),
            $request->string('justificacion')->toString(),
        );

        return $this->estado($incidencia, 'Prioridad actualizada.', ['prioridad' => $incidencia->prioridad?->only(['codigo', 'nombre'])]);
    }

    /**
     * POST /api/v1/incidencias/{id}/evaluacion-riesgo (HU-5.4, RF-004).
     */
    public function evaluarRiesgo(EvaluarRiesgoRequest $request, Incidencia $incidencia): JsonResponse
    {
        $evaluacion = $this->triaje->evaluarRiesgo($incidencia, $request->user(), $request->validated());

        return $this->estado($incidencia, $evaluacion->protocolo_activado ? 'Protocolo de emergencia activado.' : 'Evaluación de riesgo registrada.', [
            'evaluacion_id' => $evaluacion->id,
            'protocolo_activado' => $evaluacion->protocolo_activado,
            'prioridad' => $incidencia->prioridad?->only(['codigo', 'nombre']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function estado(Incidencia $incidencia, string $mensaje, array $extra = []): JsonResponse
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
