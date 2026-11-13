<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatalogoService;
use App\Services\PerfilService;
use App\Support\ContextoRol;
use Illuminate\Http\JsonResponse;

class CatalogoController extends Controller
{
    public function __construct(private CatalogoService $catalogo) {}

    /**
     * GET /api/v1/catalogo/glosario-estados (HU-2.4).
     */
    public function glosarioEstados(): JsonResponse
    {
        return response()->json(['data' => collect($this->catalogo->estados())->map(fn (array $estado) => [
            'codigo' => $estado['codigo'],
            'nombre' => $estado['nombre'],
            'significado' => $estado['significado_publico'],
            'color' => $estado['color'],
            'categoria' => $estado['categoria'],
            'requiere_accion_reportante' => (bool) $estado['requiere_accion_reportante'],
            'es_terminal' => (bool) $estado['es_terminal'],
        ])->values()]);
    }

    /**
     * GET /api/v1/catalogo/ubicaciones/arbol-activo (HU-11.1).
     */
    public function arbolUbicaciones(): JsonResponse
    {
        return response()->json(['data' => $this->catalogo->arbolUbicacionesActivas()]);
    }

    /**
     * GET /api/v1/catalogo/categorias (HU-3.1, HU-11.2).
     */
    public function categorias(): JsonResponse
    {
        return response()->json(['data' => $this->catalogo->categoriasActivas()]);
    }

    /**
     * GET /api/v1/catalogo/prioridades.
     */
    public function prioridades(): JsonResponse
    {
        return response()->json(['data' => $this->catalogo->prioridades()->map(fn ($prioridad) => $prioridad->only([
            'id', 'codigo', 'nombre', 'nivel', 'horas_primera_respuesta', 'horas_resolucion', 'color',
        ]))->values()]);
    }

    /**
     * GET /api/v1/navegacion/menu (HU-2.2): ítems habilitados según la matriz D09 para el rol activo.
     */
    public function menu(PerfilService $perfil, ContextoRol $contextoRol): JsonResponse
    {
        return response()->json([
            'rol_activo' => $contextoRol->codigo(),
            'data' => $perfil->menu($contextoRol->rol()),
        ]);
    }
}
