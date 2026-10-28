<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CategoriaEvento;
use App\Http\Controllers\Controller;
use App\Models\CategoriaIncidencia;
use App\Services\AuditoriaService;
use App\Services\CatalogoService;
use App\Services\ParametrizacionService;
use App\Services\ParametrosService;
use App\Support\Parametro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Categorías de daño con sus interruptores de negocio y parámetros del proceso (HU-11.2).
 */
class AdminCategoriaController extends Controller
{
    public function __construct(private CatalogoService $catalogo, private AuditoriaService $auditoria) {}

    /**
     * GET /api/v1/admin/categorias (incluye inactivas).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => CategoriaIncidencia::query()
            ->principales()
            ->with(['subcategorias' => fn ($query) => $query->orderBy('nombre'), 'especialidad'])
            ->orderBy('nombre')
            ->get()]);
    }

    /**
     * POST /api/v1/admin/categorias.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());

        $categoria = DB::transaction(function () use ($datos, $request): CategoriaIncidencia {
            $categoria = CategoriaIncidencia::create($datos + ['activo' => true]);
            $this->auditar('CATEGORIA_CREADA', $categoria, $request, ['nuevo' => $categoria->toArray()]);

            return $categoria;
        });

        $this->catalogo->invalidarCategorias();

        return response()->json(['data' => $categoria], 201);
    }

    /**
     * PUT /api/v1/admin/categorias/{id}: ExigeEvidenciaFotografica (RN-005), RequiereConfirmacionCierre (RN-040/041),
     * nivel de riesgo base, especialidad y estado activo.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $categoria = CategoriaIncidencia::query()->findOrFail($id);
        $datos = $request->validate($this->reglas($categoria));

        DB::transaction(function () use ($categoria, $datos, $request): void {
            $anterior = $categoria->toArray();
            $categoria->fill($datos)->save();
            $this->auditar('CATEGORIA_ACTUALIZADA', $categoria, $request, ['anterior' => $anterior, 'nuevo' => $categoria->toArray()]);
        });

        $this->catalogo->invalidarCategorias();

        return response()->json(['data' => $categoria]);
    }

    /**
     * GET /api/v1/admin/parametros-proceso: tiempos objetivo por prioridad y plazo de aclaraciones.
     */
    public function parametrosProceso(ParametrosService $parametros): JsonResponse
    {
        return response()->json(['data' => [
            'prioridades' => $this->catalogo->prioridades()->map(fn ($prioridad) => $prioridad->only(['codigo', 'nombre', 'horas_primera_respuesta', 'horas_resolucion']))->values(),
            'plazo_aclaracion_dias_habiles' => $parametros->entero(Parametro::PLAZO_ACLARACION_DIAS_HABILES),
        ]]);
    }

    /**
     * PUT /api/v1/admin/parametros-proceso.
     */
    public function actualizarParametrosProceso(Request $request, ParametrizacionService $parametrizacion): JsonResponse
    {
        $datos = $request->validate([
            'prioridades' => ['sometimes', 'array'],
            'prioridades.*.codigo' => ['required', 'exists:prioridades,codigo'],
            'prioridades.*.horas_primera_respuesta' => ['required', 'integer', 'min:1', 'max:2000'],
            'prioridades.*.horas_resolucion' => ['required', 'integer', 'min:1', 'max:5000'],
            'plazo_aclaracion_dias_habiles' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ], ParametrizacionService::MENSAJES);

        $parametrizacion->aplicar([
            'sla' => $datos['prioridades'] ?? [],
            'parametros' => isset($datos['plazo_aclaracion_dias_habiles']) ? [Parametro::PLAZO_ACLARACION_DIAS_HABILES => $datos['plazo_aclaracion_dias_habiles']] : [],
        ], $request->user());

        return $this->parametrosProceso(app(ParametrosService::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?CategoriaIncidencia $categoria = null): array
    {
        $requerido = $categoria === null ? 'required' : 'sometimes';

        return [
            'categoria_padre_id' => ['nullable', 'integer', Rule::exists('categorias_incidencia', 'id')->whereNull('categoria_padre_id'), Rule::notIn(array_filter([$categoria?->id]))],
            'codigo' => [$requerido, 'string', 'max:40', Rule::unique('categorias_incidencia', 'codigo')->ignore($categoria?->id)],
            'nombre' => [$requerido, 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'exige_evidencia_fotografica' => ['sometimes', 'boolean'],
            'requiere_confirmacion_cierre' => ['sometimes', 'boolean'],
            'nivel_riesgo_base' => ['sometimes', Rule::in(['BAJO', 'MEDIO', 'ALTO'])],
            'especialidad_id' => ['nullable', 'integer', 'exists:especialidades,id'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function auditar(string $tipo, CategoriaIncidencia $categoria, Request $request, array $datos): void
    {
        $this->auditoria->registrar(
            tipoEvento: $tipo,
            categoria: CategoriaEvento::Configuracion,
            usuario: $request->user(),
            datos: $datos,
            entidadTipo: 'categoria_incidencia',
            entidadId: $categoria->id,
        );
    }
}
