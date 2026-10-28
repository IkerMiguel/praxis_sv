<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EtapaEvidencia;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\RadicarIncidenciaRequest;
use App\Http\Requests\SubirEvidenciasRequest;
use App\Http\Resources\EvidenciaResource;
use App\Models\Evidencia;
use App\Models\Incidencia;
use App\Services\EvidenciaService;
use App\Services\RadicacionService;
use App\Support\ContextoRol;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RadicacionController extends Controller
{
    public function __construct(private RadicacionService $radicacion, private EvidenciaService $evidencias) {}

    /**
     * POST /api/v1/incidencias/radicar (HU-3.1). Admite cabecera Idempotency-Key contra doble envío.
     */
    public function radicar(RadicarIncidenciaRequest $request): JsonResponse
    {
        $clave = $request->header('Idempotency-Key');
        $incidencia = $this->radicacion->radicar($request->validated(), $request->user(), $clave ? substr($clave, 0, 100) : null);

        return response()->json(['data' => [
            'id' => $incidencia->id,
            'codigo_ticket' => $incidencia->codigo_ticket,
            'estado' => ['codigo' => $incidencia->estado_codigo->value, 'nombre' => $incidencia->estado_codigo->nombre()],
            'posible_duplicado' => $incidencia->posible_duplicado,
            'es_riesgo_inmediato' => $incidencia->es_riesgo_inmediato,
            'fecha_registro' => $incidencia->created_at?->toIso8601String(),
        ]], $incidencia->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * POST /api/v1/incidencias/evidencias/upload (HU-3.2). Sin incidencia_id las evidencias quedan
     * pendientes de vincular al radicar; con incidencia_id se asocian a la etapa indicada.
     */
    public function subirEvidencias(SubirEvidenciasRequest $request, ContextoRol $contextoRol): JsonResponse
    {
        $usuario = $request->user();
        $etapa = $request->enum('etapa', EtapaEvidencia::class) ?? EtapaEvidencia::Reporte;
        $incidencia = $request->filled('incidencia_id') ? Incidencia::query()->findOrFail($request->input('incidencia_id')) : null;

        if ($incidencia !== null && ! $this->puedeAdjuntar($incidencia, $etapa, $usuario->id, $contextoRol)) {
            throw new AuthorizationException('No está autorizado para adjuntar evidencias en esta etapa de la incidencia.');
        }

        $evidencias = $this->evidencias->cargar($request->file('archivos'), $usuario, $etapa, $incidencia);

        return response()->json(['data' => EvidenciaResource::collection($evidencias)], 201);
    }

    /**
     * GET /api/v1/evidencias/{evidencia}/descargar (URL firmada de corta duración).
     */
    public function descargar(Evidencia $evidencia): StreamedResponse
    {
        return Storage::disk($evidencia->disco)->response($evidencia->ruta_storage, $evidencia->nombre_original, [
            'Content-Type' => $evidencia->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET /api/v1/incidencias/coincidencias-activas?espacio_fisico_id=&categoria_id= (HU-3.3, RN-011).
     */
    public function coincidencias(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'espacio_fisico_id' => ['required', 'integer', 'exists:espacios_fisicos,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias_incidencia,id'],
        ]);

        $usuario = $request->user();
        $coincidencias = $this->radicacion->coincidenciasActivas((int) $datos['espacio_fisico_id'], isset($datos['categoria_id']) ? (int) $datos['categoria_id'] : null);
        $seguidas = $usuario->id === null ? [] : Incidencia::query()
            ->whereIn('id', $coincidencias->pluck('id'))
            ->whereHas('seguidores', fn ($query) => $query->where('users.id', $usuario->id))
            ->pluck('id')
            ->all();

        return response()->json([
            'hay_coincidencias' => $coincidencias->isNotEmpty(),
            'data' => $coincidencias->map(fn (Incidencia $incidencia) => [
                'id' => $incidencia->id,
                'codigo_ticket' => $incidencia->codigo_ticket,
                'resumen' => $incidencia->resumen,
                'categoria' => $incidencia->categoria->only(['id', 'nombre']),
                'estado' => ['codigo' => $incidencia->estado_codigo->value, 'nombre' => $incidencia->estado_codigo->nombre()],
                'fecha_registro' => $incidencia->created_at?->toIso8601String(),
                'es_mia' => $incidencia->esCreadaPor($usuario),
                'ya_suscrito' => in_array($incidencia->id, $seguidas, true),
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/incidencias/{incidencia}/suscribir-seguimiento (HU-3.3 escenario 2).
     */
    public function suscribir(Request $request, Incidencia $incidencia): JsonResponse
    {
        $this->radicacion->suscribir($incidencia, $request->user());

        return response()->json([
            'mensaje' => "Ahora recibirá las actualizaciones de {$incidencia->codigo_ticket}.",
            'redirect_to' => '/mis-solicitudes',
        ]);
    }

    private function puedeAdjuntar(Incidencia $incidencia, EtapaEvidencia $etapa, int $usuarioId, ContextoRol $contextoRol): bool
    {
        return match ($etapa) {
            EtapaEvidencia::Reporte, EtapaEvidencia::Aclaracion => (int) $incidencia->usuario_creador_id === $usuarioId,
            EtapaEvidencia::Inspeccion, EtapaEvidencia::Ejecucion => (int) $incidencia->tecnico_asignado_id === $usuarioId
                || $contextoRol->es(Rol::Coordinador, Rol::Administrador),
            EtapaEvidencia::Verificacion => $contextoRol->es(Rol::Verificador, Rol::Administrador),
        };
    }
}
