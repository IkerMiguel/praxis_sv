<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CategoriaEvento;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Models\Edificio;
use App\Models\EspacioFisico;
use App\Models\Incidencia;
use App\Models\Piso;
use App\Models\Sede;
use App\Services\AuditoriaService;
use App\Services\CatalogoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Catálogo jerárquico de ubicaciones (HU-11.1). Los nodos con incidencias históricas solo se inactivan.
 */
class AdminUbicacionController extends Controller
{
    public const NIVELES = ['sedes', 'edificios', 'pisos', 'espacios'];

    /**
     * nivel => [modelo, columna padre, tabla padre, columna en incidencias].
     *
     * @var array<string, array{0: class-string<Model>, 1: string|null, 2: string|null, 3: string}>
     */
    private const DEFINICION = [
        'sedes' => [Sede::class, null, null, 'sede_id'],
        'edificios' => [Edificio::class, 'sede_id', 'sedes', 'edificio_id'],
        'pisos' => [Piso::class, 'edificio_id', 'edificios', 'piso_id'],
        'espacios' => [EspacioFisico::class, 'piso_id', 'pisos', 'espacio_fisico_id'],
    ];

    private const MENSAJES = [
        'codigo.unique' => 'Esa ubicación ya se encuentra parametrizada.',
        'nombre.unique' => 'Esa ubicación ya se encuentra parametrizada.',
    ];

    public function __construct(private CatalogoService $catalogo, private AuditoriaService $auditoria) {}

    /**
     * GET /api/v1/admin/ubicaciones: árbol completo incluyendo nodos inactivos.
     */
    public function index(): JsonResponse
    {
        $sedes = Sede::query()
            ->with(['edificios' => fn ($query) => $query->orderBy('nombre'), 'edificios.pisos' => fn ($query) => $query->orderBy('nivel'), 'edificios.pisos.espacios' => fn ($query) => $query->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => $sedes]);
    }

    /**
     * POST /api/v1/admin/ubicaciones/{nivel}.
     */
    public function store(Request $request, string $nivel): JsonResponse
    {
        [$modelo, $padre] = self::DEFINICION[$nivel];
        $datos = $request->validate($this->reglas($nivel, $request), self::MENSAJES);

        $nodo = DB::transaction(function () use ($modelo, $datos, $request, $nivel): Model {
            $nodo = $modelo::create($datos + ['activo' => true]);
            $this->auditar('UBICACION_CREADA', $nivel, $nodo, $request, ['nuevo' => $nodo->toArray()]);

            return $nodo;
        });

        $this->catalogo->invalidarUbicaciones();

        return response()->json(['data' => $nodo], 201);
    }

    /**
     * PUT /api/v1/admin/ubicaciones/{nivel}/{id}.
     */
    public function update(Request $request, string $nivel, int $id): JsonResponse
    {
        $nodo = $this->nodo($nivel, $id);
        $datos = $request->validate($this->reglas($nivel, $request, $nodo), self::MENSAJES);

        DB::transaction(function () use ($nodo, $datos, $request, $nivel): void {
            $anterior = $nodo->toArray();
            $nodo->fill($datos)->save();
            $this->auditar('UBICACION_ACTUALIZADA', $nivel, $nodo, $request, ['anterior' => $anterior, 'nuevo' => $nodo->toArray()]);
        });

        $this->catalogo->invalidarUbicaciones();

        return response()->json(['data' => $nodo]);
    }

    /**
     * PATCH /api/v1/admin/ubicaciones/{nivel}/{id}/estado: inactivación lógica.
     */
    public function cambiarEstado(Request $request, string $nivel, int $id): JsonResponse
    {
        $nodo = $this->nodo($nivel, $id);
        $datos = $request->validate(['activo' => ['required', 'boolean'], 'motivo' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($nodo, $datos, $request, $nivel): void {
            $nodo->forceFill(['activo' => (bool) $datos['activo']])->save();
            $this->auditar($datos['activo'] ? 'UBICACION_ACTIVADA' : 'UBICACION_INACTIVADA', $nivel, $nodo, $request, ['motivo' => $datos['motivo'] ?? null]);
        });

        $this->catalogo->invalidarUbicaciones();

        return response()->json(['data' => $nodo]);
    }

    /**
     * DELETE /api/v1/admin/ubicaciones/{nivel}/{id}: solo nodos sin historial ni dependientes.
     */
    public function destroy(Request $request, string $nivel, int $id): JsonResponse
    {
        $nodo = $this->nodo($nivel, $id);
        $columna = self::DEFINICION[$nivel][3];

        if (Incidencia::query()->where($columna, $id)->exists()) {
            throw ReglaNegocioException::conflicto('La ubicación tiene incidencias históricas asociadas; solo puede inactivarse.', 'HU-11.1');
        }

        $hijos = match ($nivel) {
            'sedes' => Edificio::query()->where('sede_id', $id)->exists(),
            'edificios' => Piso::query()->where('edificio_id', $id)->exists(),
            'pisos' => EspacioFisico::query()->where('piso_id', $id)->exists(),
            default => false,
        };

        if ($hijos) {
            throw ReglaNegocioException::conflicto('La ubicación tiene niveles dependientes; elimínelos o inactívela.', 'HU-11.1');
        }

        DB::transaction(function () use ($nodo, $request, $nivel): void {
            $this->auditar('UBICACION_ELIMINADA', $nivel, $nodo, $request, ['anterior' => $nodo->toArray()]);
            $nodo->delete();
        });

        $this->catalogo->invalidarUbicaciones();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(string $nivel, Request $request, ?Model $nodo = null): array
    {
        [$modelo, $padre, $tablaPadre] = self::DEFINICION[$nivel];
        $tabla = (new $modelo)->getTable();
        $padreId = $padre === null ? null : ($request->input($padre) ?? $nodo?->{$padre});
        $requerido = $nodo === null ? 'required' : 'sometimes';

        $unico = fn (string $columna) => Rule::unique($tabla, $columna)
            ->when($padre !== null, fn ($regla) => $regla->where($padre, $padreId))
            ->ignore($nodo?->getKey());

        $reglas = [
            'codigo' => [$requerido, 'string', 'max:30', $unico('codigo')],
            'nombre' => [$requerido, 'string', 'max:120', $unico('nombre')],
        ];

        if ($padre !== null) {
            $reglas[$padre] = [$requerido, 'integer', "exists:{$tablaPadre},id"];
        }

        return $reglas + match ($nivel) {
            'sedes' => ['direccion' => ['nullable', 'string', 'max:255']],
            'pisos' => ['nivel' => ['nullable', 'integer', 'between:-10,200']],
            'espacios' => ['tipo' => ['nullable', 'string', 'max:40']],
            default => [],
        };
    }

    private function nodo(string $nivel, int $id): Model
    {
        return self::DEFINICION[$nivel][0]::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function auditar(string $tipo, string $nivel, Model $nodo, Request $request, array $datos): void
    {
        $this->auditoria->registrar(
            tipoEvento: $tipo,
            categoria: CategoriaEvento::Configuracion,
            usuario: $request->user(),
            datos: $datos,
            entidadTipo: $nivel,
            entidadId: $nodo->getKey(),
        );
    }
}
