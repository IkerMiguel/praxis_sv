<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CategoriaEvento;
use App\Enums\CodigoPrioridad;
use App\Enums\Impacto;
use App\Enums\Urgencia;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventoAuditoriaResource;
use App\Models\EventoAuditoria;
use App\Models\MatrizPrioridad;
use App\Models\ParametroSistema;
use App\Services\CatalogoService;
use App\Services\ParametrizacionService;
use App\Support\Parametro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Parametrización de reglas del sistema: matriz ITIL, SLA, capacidad y ventanas (HU-11.3).
 */
class AdminParametroController extends Controller
{
    /**
     * GET /api/v1/admin/parametros.
     */
    public function index(CatalogoService $catalogo): JsonResponse
    {
        return response()->json(['data' => [
            'matriz_prioridad' => MatrizPrioridad::query()->with('prioridad')->orderBy('impacto')->orderBy('urgencia')->get()
                ->map(fn (MatrizPrioridad $celda) => ['impacto' => $celda->impacto, 'urgencia' => $celda->urgencia, 'prioridad' => $celda->prioridad->codigo])
                ->values(),
            'sla' => $catalogo->prioridades()->map(fn ($prioridad) => $prioridad->only(['codigo', 'nombre', 'horas_primera_respuesta', 'horas_resolucion']))->values(),
            'parametros' => ParametroSistema::query()->orderBy('grupo')->orderBy('clave')->get()->map(fn (ParametroSistema $parametro) => [
                'clave' => $parametro->clave,
                'valor' => $parametro->valorTipado(),
                'tipo_dato' => $parametro->tipo_dato,
                'grupo' => $parametro->grupo,
                'descripcion' => $parametro->descripcion,
                'actualizado_en' => $parametro->updated_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    /**
     * PUT /api/v1/admin/parametros: recarga en caliente (invalida la caché al guardar).
     */
    public function update(Request $request, ParametrizacionService $parametrizacion, CatalogoService $catalogo): JsonResponse
    {
        $datos = $request->validate([
            'matriz' => ['sometimes', 'array'],
            'matriz.*.impacto' => ['required', Rule::enum(Impacto::class)],
            'matriz.*.urgencia' => ['required', Rule::enum(Urgencia::class)],
            'matriz.*.prioridad' => ['required', Rule::enum(CodigoPrioridad::class)],
            'sla' => ['sometimes', 'array'],
            'sla.*.codigo' => ['required', Rule::enum(CodigoPrioridad::class)],
            'sla.*.horas_primera_respuesta' => ['required', 'integer', 'min:1', 'max:2000'],
            'sla.*.horas_resolucion' => ['required', 'integer', 'min:1', 'max:5000'],
            'parametros' => ['sometimes', 'array'],
            'parametros.*' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], ParametrizacionService::MENSAJES);

        $desconocidos = array_diff(array_keys($datos['parametros'] ?? []), array_keys(Parametro::porDefecto()));

        if ($desconocidos !== []) {
            return response()->json([
                'type' => 'https://praxis.ecci.edu.co/problemas/regla-de-negocio',
                'title' => 'Los datos enviados no son válidos.',
                'status' => 422,
                'errors' => ['parametros' => ['Parámetros desconocidos: '.implode(', ', $desconocidos)]],
            ], 422, ['Content-Type' => 'application/problem+json']);
        }

        $parametrizacion->aplicar($datos, $request->user());

        return $this->index($catalogo);
    }

    /**
     * GET /api/v1/admin/parametros/historial: bitácora de cambios de configuración (D10).
     */
    public function historial(Request $request): AnonymousResourceCollection
    {
        return EventoAuditoriaResource::collection(EventoAuditoria::query()
            ->where('categoria', CategoriaEvento::Configuracion->value)
            ->with('usuario:id,name')
            ->orderByDesc('fecha_hora')
            ->paginate($request->integer('per_page', 50)));
    }
}
