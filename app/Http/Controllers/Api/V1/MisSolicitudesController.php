<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoIncidencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelarSolicitudRequest;
use App\Http\Requests\ConfirmarSolucionRequest;
use App\Http\Requests\MotivoRequest;
use App\Http\Requests\ReabrirIncidenciaRequest;
use App\Http\Requests\ResponderAclaracionRequest;
use App\Http\Resources\IncidenciaResumenResource;
use App\Models\Incidencia;
use App\Models\User;
use App\Services\DetalleIncidenciaService;
use App\Services\ReportanteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MisSolicitudesController extends Controller
{
    public function __construct(private ReportanteService $reportante) {}

    /**
     * GET /api/v1/mis-solicitudes?estado=&q=&page= (HU-4.1). Filtra siempre por el usuario autenticado.
     * ambito=propias (por defecto) | seguidas | todas.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'estado' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'ambito' => ['nullable', Rule::in(['propias', 'seguidas', 'todas'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $usuario = $request->user();
        $estados = collect(explode(',', (string) $request->input('estado')))
            ->map(fn (string $codigo) => EstadoIncidencia::tryFrom(strtoupper(trim($codigo))))
            ->filter()
            ->values()
            ->all();
        $texto = trim((string) $request->input('q'));

        $solicitudes = $this->visibles($usuario, (string) $request->input('ambito', 'propias'))
            ->when($estados !== [], fn (Builder $query) => $query->enEstados($estados))
            ->when($texto !== '', fn (Builder $query) => $query->where(fn (Builder $busqueda) => $busqueda
                ->whereRaw('LOWER(codigo_ticket) LIKE ?', ['%'.mb_strtolower($texto).'%'])
                ->orWhereRaw('LOWER(resumen) LIKE ?', ['%'.mb_strtolower($texto).'%'])))
            ->with(['estado', 'categoria', 'prioridad'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return IncidenciaResumenResource::collection($solicitudes)->additional(['meta' => [
            'filtros' => ['estado' => array_map(fn (EstadoIncidencia $estado) => $estado->value, $estados), 'q' => $texto ?: null],
            'sin_resultados' => $solicitudes->total() === 0,
        ]]);
    }

    /**
     * GET /api/v1/mis-solicitudes/{id} (HU-4.2). Las solicitudes ajenas responden 404 (anti-IDOR).
     */
    public function show(Request $request, string $incidencia, DetalleIncidenciaService $detalle): JsonResponse
    {
        $registro = $this->visibles($request->user(), 'todas')->whereKey($incidencia)->firstOrFail();

        return response()->json(['data' => $detalle->paraReportante($registro, $request->user())]);
    }

    /**
     * POST /api/v1/mis-solicitudes/{id}/aclaraciones (HU-4.3): E02 -> E03.
     */
    public function responderAclaracion(ResponderAclaracionRequest $request, string $incidencia): JsonResponse
    {
        $registro = $this->propia($request->user(), $incidencia);
        $aclaracion = $this->reportante->responderAclaracion(
            $registro,
            $request->user(),
            $request->string('respuesta')->toString(),
            array_map('intval', $request->input('evidencias', [])),
        );

        return response()->json([
            'mensaje' => 'Aclaración enviada. La solicitud volvió a revisión.',
            'data' => ['aclaracion_id' => $aclaracion->id, 'estado' => $registro->estado_codigo->value],
        ]);
    }

    /**
     * POST /api/v1/incidencias/{id}/cancelar-solicitante (HU-4.4): E01-E04 -> E17.
     */
    public function cancelar(CancelarSolicitudRequest $request, string $incidencia): JsonResponse
    {
        $registro = $this->propia($request->user(), $incidencia);
        $this->reportante->cancelar($registro, $request->user(), $request->string('motivo')->toString());

        return response()->json(['mensaje' => 'La solicitud fue cancelada.', 'data' => ['estado' => $registro->estado_codigo->value]]);
    }

    /**
     * POST /api/v1/incidencias/{id}/reabrir (HU-4.5).
     */
    public function reabrir(ReabrirIncidenciaRequest $request, string $incidencia): JsonResponse
    {
        $registro = $this->propia($request->user(), $incidencia);
        $this->reportante->reabrir($registro, $request->user(), $request->string('motivo')->toString());

        return response()->json([
            'mensaje' => 'La solicitud fue reabierta y la coordinación fue notificada.',
            'data' => ['estado' => $registro->estado_codigo->value, 'numero_reaperturas' => $registro->numero_reaperturas],
        ]);
    }

    /**
     * POST /api/v1/incidencias/{id}/confirmar (HU-9.3): E14 -> E15.
     */
    public function confirmar(ConfirmarSolucionRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->reportante->confirmar($incidencia, $request->user(), $request->integer('calificacion'), $request->input('comentario'));

        return response()->json(['mensaje' => 'Solución confirmada. La incidencia fue cerrada.', 'data' => ['estado' => $incidencia->estado_codigo->value]]);
    }

    /**
     * POST /api/v1/incidencias/{id}/rechazar-conformidad (HU-9.3): E14 -> E13.
     */
    public function rechazarConformidad(MotivoRequest $request, Incidencia $incidencia): JsonResponse
    {
        $this->reportante->rechazarConformidad($incidencia, $request->user(), $request->string('motivo')->toString());

        return response()->json(['mensaje' => 'Se registró el rechazo. La incidencia volvió a corrección.', 'data' => ['estado' => $incidencia->estado_codigo->value]]);
    }

    /**
     * @return Builder<Incidencia>
     */
    private function visibles(User $usuario, string $ambito): Builder
    {
        return match ($ambito) {
            'seguidas' => Incidencia::query()->whereHas('seguidores', fn (Builder $query) => $query->where('users.id', $usuario->id)),
            'todas' => Incidencia::query()->where(fn (Builder $query) => $query
                ->where('usuario_creador_id', $usuario->id)
                ->orWhereHas('seguidores', fn (Builder $seguidores) => $seguidores->where('users.id', $usuario->id))),
            default => Incidencia::query()->where('usuario_creador_id', $usuario->id),
        };
    }

    private function propia(User $usuario, string $incidencia): Incidencia
    {
        return Incidencia::query()->where('usuario_creador_id', $usuario->id)->whereKey($incidencia)->firstOrFail();
    }
}
