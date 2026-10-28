<?php

namespace App\Services;

use App\Enums\Rol;
use App\Models\User;
use App\Support\ContextoRol;

/**
 * Perfil activo del usuario y menú dinámico según la matriz de permisos D09 (HU-1.3, HU-2.1, HU-2.2).
 */
class PerfilService
{
    public function __construct(private CatalogoService $catalogo, private ContextoRol $contextoRol) {}

    /**
     * @return array<string, mixed>
     */
    public function perfil(User $usuario, ?Rol $rolActivo = null): array
    {
        $usuario->loadMissing(['roles', 'especialidades']);
        $rolActivo ??= $this->contextoRol->rol() ?? (isset($usuario->roles[0]) ? Rol::from($usuario->roles[0]->codigo) : null);

        return [
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
            'activo' => $usuario->activo,
            'roles' => $usuario->roles->map(fn ($rol) => [
                'codigo' => $rol->codigo,
                'nombre' => $rol->nombre,
                'ruta_inicio' => $rol->ruta_inicio,
            ])->values()->all(),
            'rol_activo' => $rolActivo === null ? null : ['codigo' => $rolActivo->value, 'nombre' => $rolActivo->nombre()],
            'ruta_inicio' => $rolActivo?->rutaInicio(),
            'especialidades' => $usuario->especialidades->map(fn ($especialidad) => $especialidad->only(['id', 'codigo', 'nombre']))->values()->all(),
            'permisos' => $rolActivo === null ? [] : collect($this->catalogo->permisosPorRol()[$rolActivo->value] ?? [])
                ->map(fn (array $modulo) => ['modulo' => $modulo['codigo'], 'nivel_acceso' => $modulo['nivel_acceso']])
                ->values()
                ->all(),
        ];
    }

    /**
     * Ítems de menú habilitados para el rol activo.
     *
     * @return list<array<string, mixed>>
     */
    public function menu(?Rol $rolActivo): array
    {
        if ($rolActivo === null) {
            return [];
        }

        return collect($this->catalogo->permisosPorRol()[$rolActivo->value] ?? [])
            ->filter(fn (array $modulo) => $modulo['en_menu'])
            ->sortBy('orden')
            ->map(fn (array $modulo) => [
                'codigo' => $modulo['codigo'],
                'etiqueta' => $modulo['etiqueta'],
                'ruta' => $modulo['ruta'],
                'icono' => $modulo['icono'],
                'grupo' => $modulo['grupo'],
                'solo_lectura' => $modulo['nivel_acceso'] === 'LECTURA',
                'nivel_acceso' => $modulo['nivel_acceso'],
            ])
            ->values()
            ->all();
    }
}
