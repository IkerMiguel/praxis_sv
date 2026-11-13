<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoIncidencia;
use App\Http\Controllers\Controller;
use App\Http\Resources\IncidenciaResumenResource;
use App\Models\Incidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    /**
     * GET /api/v1/panel/resumen (HU-2.3): conteo por estado de las solicitudes propias (GROUP BY estado).
     * La suma de contadores coincide con el total de /mis-solicitudes.
     */
    public function resumen(Request $request): JsonResponse
    {
        $usuarioId = $request->user()->id;

        $conteos = Incidencia::query()
            ->where('usuario_creador_id', $usuarioId)
            ->selectRaw('estado_codigo, COUNT(*) as total')
            ->groupBy('estado_codigo')
            ->pluck('total', 'estado_codigo')
            ->map(fn ($total) => (int) $total);

        $porEstado = collect(EstadoIncidencia::cases())->map(fn (EstadoIncidencia $estado) => [
            'codigo' => $estado->value,
            'nombre' => $estado->nombre(),
            'total' => $conteos[$estado->value] ?? 0,
        ])->values();

        $recientes = Incidencia::query()
            ->where('usuario_creador_id', $usuarioId)
            ->with(['estado', 'categoria'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json(['data' => [
            'total' => $conteos->sum(),
            'por_estado' => $porEstado,
            'requieren_accion' => [
                'pendiente_informacion' => $conteos[EstadoIncidencia::PendienteInformacion->value] ?? 0,
                'pendiente_confirmacion' => $conteos[EstadoIncidencia::PendienteConfirmacion->value] ?? 0,
            ],
            'activas' => $conteos->except(EstadoIncidencia::valores([EstadoIncidencia::Cerrada, EstadoIncidencia::NoProcedente, EstadoIncidencia::Cancelada]))->sum(),
            'recientes' => IncidenciaResumenResource::collection($recientes),
        ]]);
    }
}
