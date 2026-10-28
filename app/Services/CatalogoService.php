<?php

namespace App\Services;

use App\Enums\Impacto;
use App\Enums\Urgencia;
use App\Models\CategoriaIncidencia;
use App\Models\Estado;
use App\Models\MatrizPrioridad;
use App\Models\Prioridad;
use App\Models\Rol;
use App\Models\Sede;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogos estáticos en caché con invalidación al modificarse en administración (D15).
 */
class CatalogoService
{
    public const CACHE_UBICACIONES = 'praxis:catalogo:ubicaciones';

    public const CACHE_CATEGORIAS = 'praxis:catalogo:categorias';

    public const CACHE_MATRIZ = 'praxis:catalogo:matriz';

    public const CACHE_PRIORIDADES = 'praxis:catalogo:prioridades';

    public const CACHE_ESTADOS = 'praxis:catalogo:estados';

    public const CACHE_PERMISOS = 'praxis:catalogo:permisos';

    /**
     * Árbol de ubicaciones activas Sede > Edificio > Piso > Espacio (HU-11.1).
     *
     * @return list<array<string, mixed>>
     */
    public function arbolUbicacionesActivas(): array
    {
        return Cache::rememberForever(self::CACHE_UBICACIONES, function (): array {
            return Sede::query()
                ->activos()
                ->orderBy('nombre')
                ->with([
                    'edificios' => fn ($query) => $query->activos()->orderBy('nombre'),
                    'edificios.pisos' => fn ($query) => $query->activos()->orderBy('nivel'),
                    'edificios.pisos.espacios' => fn ($query) => $query->activos()->orderBy('nombre'),
                ])
                ->get()
                ->map(fn (Sede $sede) => [
                    'id' => $sede->id,
                    'codigo' => $sede->codigo,
                    'nombre' => $sede->nombre,
                    'edificios' => $sede->edificios->map(fn ($edificio) => [
                        'id' => $edificio->id,
                        'codigo' => $edificio->codigo,
                        'nombre' => $edificio->nombre,
                        'pisos' => $edificio->pisos->map(fn ($piso) => [
                            'id' => $piso->id,
                            'codigo' => $piso->codigo,
                            'nombre' => $piso->nombre,
                            'nivel' => $piso->nivel,
                            'espacios' => $piso->espacios->map(fn ($espacio) => [
                                'id' => $espacio->id,
                                'codigo' => $espacio->codigo,
                                'nombre' => $espacio->nombre,
                                'tipo' => $espacio->tipo,
                            ])->values()->all(),
                        ])->values()->all(),
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Categorías principales activas con sus subcategorías (HU-3.1, HU-11.2).
     *
     * @return list<array<string, mixed>>
     */
    public function categoriasActivas(): array
    {
        return Cache::rememberForever(self::CACHE_CATEGORIAS, function (): array {
            return CategoriaIncidencia::query()
                ->activos()
                ->principales()
                ->with(['subcategorias' => fn ($query) => $query->activos()->orderBy('nombre'), 'especialidad'])
                ->orderBy('nombre')
                ->get()
                ->map(fn (CategoriaIncidencia $categoria) => [
                    'id' => $categoria->id,
                    'codigo' => $categoria->codigo,
                    'nombre' => $categoria->nombre,
                    'descripcion' => $categoria->descripcion,
                    'exige_evidencia_fotografica' => $categoria->exige_evidencia_fotografica,
                    'requiere_confirmacion_cierre' => $categoria->requiere_confirmacion_cierre,
                    'nivel_riesgo_base' => $categoria->nivel_riesgo_base,
                    'especialidad' => $categoria->especialidad?->only(['id', 'codigo', 'nombre']),
                    'subcategorias' => $categoria->subcategorias->map(fn (CategoriaIncidencia $sub) => [
                        'id' => $sub->id,
                        'codigo' => $sub->codigo,
                        'nombre' => $sub->nombre,
                        'exige_evidencia_fotografica' => $sub->exige_evidencia_fotografica,
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Matriz ITIL impacto x urgencia => id de prioridad (HU-11.3).
     *
     * @return array<string, int>
     */
    public function matrizPrioridad(): array
    {
        return Cache::rememberForever(self::CACHE_MATRIZ, fn (): array => MatrizPrioridad::query()
            ->get()
            ->mapWithKeys(fn (MatrizPrioridad $celda) => [$celda->impacto.'|'.$celda->urgencia => (int) $celda->prioridad_id])
            ->all());
    }

    public function prioridadPara(Impacto $impacto, Urgencia $urgencia): ?Prioridad
    {
        $prioridadId = $this->matrizPrioridad()[$impacto->value.'|'.$urgencia->value] ?? null;

        return $prioridadId === null ? null : $this->prioridades()->firstWhere('id', $prioridadId);
    }

    /**
     * @return Collection<int, Prioridad>
     */
    public function prioridades(): Collection
    {
        return Cache::rememberForever(self::CACHE_PRIORIDADES, fn () => Prioridad::query()->orderByDesc('nivel')->get());
    }

    public function prioridadPorCodigo(string $codigo): ?Prioridad
    {
        return $this->prioridades()->firstWhere('codigo', $codigo);
    }

    /**
     * Glosario de estados D07 (HU-2.4).
     *
     * @return list<array<string, mixed>>
     */
    public function estados(): array
    {
        return Cache::rememberForever(self::CACHE_ESTADOS, fn (): array => Estado::query()->orderBy('orden')->get()->toArray());
    }

    /**
     * Permisos D09 por rol: código de rol => lista de módulos con nivel de acceso.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function permisosPorRol(): array
    {
        return Cache::rememberForever(self::CACHE_PERMISOS, fn (): array => Rol::query()
            ->with(['modulos' => fn ($query) => $query->orderBy('orden')])
            ->get()
            ->mapWithKeys(fn (Rol $rol) => [$rol->codigo => $rol->modulos->map(fn ($modulo) => [
                'codigo' => $modulo->codigo,
                'etiqueta' => $modulo->etiqueta,
                'ruta' => $modulo->ruta,
                'icono' => $modulo->icono,
                'grupo' => $modulo->grupo,
                'orden' => $modulo->orden,
                'en_menu' => $modulo->en_menu,
                'nivel_acceso' => $modulo->pivot->nivel_acceso,
            ])->values()->all()])
            ->all());
    }

    public function invalidarUbicaciones(): void
    {
        Cache::forget(self::CACHE_UBICACIONES);
    }

    public function invalidarCategorias(): void
    {
        Cache::forget(self::CACHE_CATEGORIAS);
    }

    public function invalidarMatrizYPrioridades(): void
    {
        Cache::forget(self::CACHE_MATRIZ);
        Cache::forget(self::CACHE_PRIORIDADES);
    }
}
