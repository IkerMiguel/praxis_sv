<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsignarTecnicoRequest;
use App\Http\Requests\CierreAdministrativoRequest;
use App\Http\Requests\MotivoRequest;
use App\Http\Requests\ObservacionesRequest;
use App\Http\Requests\ProgramarIntervencionRequest;
use App\Http\Requests\ReanudarIncidenciaRequest;
use App\Http\Requests\ReprogramarIntervencionRequest;
use App\Http\Requests\SuspenderIncidenciaRequest;
use App\Models\Incidencia;
use App\Services\PlanificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanificacionController extends Controller
{
    public function __construct(private PlanificacionService $planificacion) {}

    /**
     * GET /api/v1/tecnicos/disponibles?categoria_id= (HU-6.2, GetTecnicosDisponiblesQuery).
     */
    public function tecnicosDisponibles(Request $request): JsonResponse
    {
        $datos = $request->validate(['categoria_id' => ['nullable', 'integer', 'exists:categorias_incidencia,id']]);

        return response()->json(['data' => $this->planificacion->tecnicosDisponibles(isset($datos['categoria_id']) ? (int) $datos['categoria_id'] : null)]);
    }

    /**
     * POST /api/v1/incidencias/{id}/asignar (HU-6.1, HU-6.2): E05 -> E06 o reasignación (RN-025).
     */
    public function asignar(AsignarTecnicoRequest $request, Incidencia $incidencia): JsonResponse
    {
        $asignacion = $this->planificacion->asignar($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, $asignacion->tecnico_anterior_id ? 'Técnico reasignado.' : 'Técnico asignado.', [
            'asignacion_id' => $asignacion->id,
            'tecnico_id' => $asignacion->tecnico_asignado_id,
            'excepcion_wip' => $asignacion->excepcion_wip,
        ]);
    }

    /**
     * POST /api/v1/incidencias/{id}/programar (HU-6.3): E07 -> E10.
     */
    public function programar(ProgramarIntervencionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $plan = $this->planificacion->programar($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Intervención programada.', ['plan' => $this->planificacion->datosPlan($plan)]);
    }

    /**
     * PATCH /api/v1/incidencias/{id}/reprogramar (HU-6.3 escenario 4).
     */
    public function reprogramar(ReprogramarIntervencionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $plan = $this->planificacion->reprogramar($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Intervención reprogramada.', ['plan' => $this->planificacion->datosPlan($plan)]);
    }

    /**
     * POST /api/v1/incidencias/{id}/devolver-diagnostico (HU-6.3): E07 -> E06.
     */
    public function devolverDiagnostico(ObservacionesRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->planificacion->devolverDiagnostico($incidencia, $request->user(), $request->string('observaciones')->toString());

        return $this->respuesta($incidencia, 'Diagnóstico devuelto al técnico.');
    }

    /**
     * POST /api/v1/incidencias/{id}/cerrar-no-confirmada (HU-6.3, HU-7.2, RN-024): E07 -> E15.
     */
    public function cerrarNoConfirmada(Request $request, Incidencia $incidencia): JsonResponse
    {
        $request->validate(['observaciones' => ['nullable', 'string', 'max:1000']]);
        $this->planificacion->cerrarNoConfirmada($incidencia, $request->user(), $request->input('observaciones'));

        return $this->respuesta($incidencia, 'Cierre justificado por incidencia no confirmada.');
    }

    /**
     * POST /api/v1/incidencias/{id}/planificar-correccion (HU-9.2): E13 -> E10.
     */
    public function planificarCorreccion(ProgramarIntervencionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $plan = $this->planificacion->planificarCorreccion($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Corrección programada.', ['plan' => $this->planificacion->datosPlan($plan)]);
    }

    /**
     * POST /api/v1/incidencias/{id}/suspender (HU-7.4): -> E18.
     */
    public function suspender(SuspenderIncidenciaRequest $request, Incidencia $incidencia): JsonResponse
    {
        $suspension = $this->planificacion->suspender($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Atención suspendida.', ['suspension_id' => $suspension->id]);
    }

    /**
     * POST /api/v1/incidencias/{id}/reanudar (HU-7.4): E18 -> E10/E11.
     */
    public function reanudar(ReanudarIncidenciaRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->planificacion->reanudar($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Atención reanudada.');
    }

    /**
     * POST /api/v1/incidencias/{id}/anular (HU-8.3 escenario 3): E09/E18 -> E17.
     */
    public function anular(MotivoRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->planificacion->anular($incidencia, $request->user(), $request->string('motivo')->toString());

        return $this->respuesta($incidencia, 'Incidencia cancelada.');
    }

    /**
     * POST /api/v1/incidencias/{id}/cierre-administrativo (HU-9.3 escenario 3): E14 -> E15.
     */
    public function cierreAdministrativo(CierreAdministrativoRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->planificacion->cierreAdministrativo($incidencia, $request->user(), $request->input('notas'));

        return $this->respuesta($incidencia, 'Cierre administrativo registrado.');
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
