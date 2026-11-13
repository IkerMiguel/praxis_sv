<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificacionResource;
use App\Models\NotificacionPlataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificacionController extends Controller
{
    /**
     * GET /api/v1/notificaciones (HU-2.5). Incluye el contador de no leídas para el badge.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['solo_no_leidas' => ['sometimes', 'boolean'], 'per_page' => ['sometimes', 'integer', 'between:1,100']]);

        $notificaciones = NotificacionPlataforma::query()
            ->where('usuario_destino_id', $request->user()->id)
            ->when($request->boolean('solo_no_leidas'), fn ($query) => $query->where('leida', false))
            ->with('incidencia:id,codigo_ticket')
            ->orderByDesc('fecha_envio')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return NotificacionResource::collection($notificaciones)->additional(['meta' => [
            'no_leidas' => NotificacionPlataforma::query()->where('usuario_destino_id', $request->user()->id)->where('leida', false)->count(),
        ]]);
    }

    /**
     * PATCH /api/v1/notificaciones/{id}/leer.
     */
    public function leer(Request $request, int $notificacion): JsonResponse
    {
        $registro = NotificacionPlataforma::query()
            ->where('usuario_destino_id', $request->user()->id)
            ->findOrFail($notificacion);

        if (! $registro->leida) {
            $registro->forceFill(['leida' => true, 'fecha_lectura' => now()])->save();
        }

        return response()->json([
            'data' => new NotificacionResource($registro->load('incidencia:id,codigo_ticket')),
            'meta' => ['no_leidas' => NotificacionPlataforma::query()->where('usuario_destino_id', $request->user()->id)->where('leida', false)->count()],
        ]);
    }

    /**
     * PATCH /api/v1/notificaciones/leer-todas.
     */
    public function leerTodas(Request $request): JsonResponse
    {
        $marcadas = NotificacionPlataforma::query()
            ->where('usuario_destino_id', $request->user()->id)
            ->where('leida', false)
            ->update(['leida' => true, 'fecha_lectura' => now()]);

        return response()->json(['marcadas' => $marcadas, 'meta' => ['no_leidas' => 0]]);
    }
}
