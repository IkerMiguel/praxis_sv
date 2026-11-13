<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Rol as RolCodigo;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\Especialidad;
use App\Models\Rol;
use App\Models\User;
use App\Services\AdministracionUsuariosService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUsuarioController extends Controller
{
    public function __construct(private AdministracionUsuariosService $usuarios) {}

    /**
     * GET /api/v1/admin/usuarios?q=&rol=&activo= (HU-1.2).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::enum(RolCodigo::class)],
            'activo' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $texto = mb_strtolower(trim((string) $request->input('q')));
        $paginado = User::query()
            ->with(['roles', 'especialidades'])
            ->when($texto !== '', fn (Builder $query) => $query->where(fn (Builder $busqueda) => $busqueda
                ->whereRaw('LOWER(name) LIKE ?', ["%{$texto}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$texto}%"])))
            ->when($request->filled('rol'), fn (Builder $query) => $query->conRol(RolCodigo::from($request->input('rol'))))
            ->when($request->has('activo'), fn (Builder $query) => $query->where('activo', $request->boolean('activo')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $paginado->getCollection()->transform(fn (User $usuario) => $this->usuario($usuario));

        return response()->json([
            'data' => $paginado->items(),
            'meta' => ['current_page' => $paginado->currentPage(), 'last_page' => $paginado->lastPage(), 'total' => $paginado->total()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->usuario(User::query()->with(['roles', 'especialidades'])->findOrFail($id))]);
    }

    /**
     * POST /api/v1/admin/usuarios.
     */
    public function store(GuardarUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarios->crear($request->validated(), $request->user());

        return response()->json(['data' => $this->usuario($usuario->load(['roles', 'especialidades']))], 201);
    }

    /**
     * PUT /api/v1/admin/usuarios/{id}.
     */
    public function update(GuardarUsuarioRequest $request, int $id): JsonResponse
    {
        $usuario = $this->usuarios->actualizar(User::query()->findOrFail($id), $request->validated(), $request->user());

        return response()->json(['data' => $this->usuario($usuario->load(['roles', 'especialidades']))]);
    }

    /**
     * PUT /api/v1/admin/usuarios/{id}/roles: asignación multi-rol (D03).
     */
    public function actualizarRoles(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::enum(RolCodigo::class)],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $usuario = $this->usuarios->actualizarRoles(User::query()->findOrFail($id), array_values(array_unique($datos['roles'])), $request->user(), $datos['motivo'] ?? null);

        return response()->json(['data' => $this->usuario($usuario->load('especialidades'))]);
    }

    /**
     * PATCH /api/v1/admin/usuarios/{id}/estado: activación o inactivación lógica (sin DELETE físico).
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['activo' => ['required', 'boolean'], 'motivo' => ['nullable', 'string', 'max:500']]);
        $usuario = $this->usuarios->cambiarEstado(User::query()->findOrFail($id), (bool) $datos['activo'], $request->user(), $datos['motivo'] ?? null);

        return response()->json(['data' => $this->usuario($usuario->load(['roles', 'especialidades']))]);
    }

    public function roles(): JsonResponse
    {
        return response()->json(['data' => Rol::query()->orderBy('orden')->get(['id', 'codigo', 'nombre', 'descripcion', 'ruta_inicio'])]);
    }

    public function especialidades(): JsonResponse
    {
        return response()->json(['data' => Especialidad::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'activo'])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function usuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
            'activo' => $usuario->activo,
            'bloqueado_hasta' => $usuario->bloqueado_hasta?->toIso8601String(),
            'ultimo_acceso_en' => $usuario->ultimo_acceso_en?->toIso8601String(),
            'roles' => $usuario->roles->map(fn (Rol $rol) => $rol->only(['codigo', 'nombre']))->values(),
            'especialidades' => $usuario->especialidades->map(fn (Especialidad $especialidad) => $especialidad->only(['codigo', 'nombre']))->values(),
            'fecha_creacion' => $usuario->created_at?->toIso8601String(),
        ];
    }
}
