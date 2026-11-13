<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoIncidencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\RechazarVerificacionRequest;
use App\Http\Requests\VerificarIntervencionRequest;
use App\Http\Resources\IncidenciaResumenResource;
use App\Models\Incidencia;
use App\Services\VerificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VerificacionController extends Controller
{
    public function __construct(private VerificacionService $verificacion) {}

    /**
     * GET /api/v1/verificacion/pendientes: bandeja E12 del verificador.
     */
    public function pendientes(Request $request): AnonymousResourceCollection
    {
        return IncidenciaResumenResource::collection(Incidencia::query()
            ->enEstados([EstadoIncidencia::PendienteVerificacion])
            ->with(['estado', 'categoria', 'prioridad', 'tecnicoAsignado', 'sede', 'edificio', 'piso', 'espacioFisico'])
            ->orderBy('fecha_ultimo_cambio_estado')
            ->paginate($request->integer('per_page', 20)));
    }

    /**
     * POST /api/v1/incidencias/{id}/verificar (HU-9.1): E12 -> E14 o E15.
     */
    public function verificar(VerificarIntervencionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $verificacion = $this->verificacion->aprobar($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Verificación conforme registrada.', $verificacion->id);
    }

    /**
     * POST /api/v1/incidencias/{id}/rechazar-verificacion (HU-9.2): E12 -> E13.
     */
    public function rechazar(RechazarVerificacionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $verificacion = $this->verificacion->rechazar($incidencia, $request->user(), $request->validated());

        return $this->respuesta($incidencia, 'Verificación no conforme registrada. La incidencia pasó a corrección.', $verificacion->id);
    }

    private function respuesta(Incidencia $incidencia, string $mensaje, int $verificacionId): JsonResponse
    {
        return response()->json([
            'mensaje' => $mensaje,
            'data' => [
                'id' => $incidencia->id,
                'codigo_ticket' => $incidencia->codigo_ticket,
                'verificacion_id' => $verificacionId,
                'estado' => ['codigo' => $incidencia->estado_codigo->value, 'nombre' => $incidencia->estado_codigo->nombre()],
            ],
        ]);
    }
}
