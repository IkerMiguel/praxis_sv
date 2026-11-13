<?php

namespace Database\Factories;

use App\Enums\EstadoIncidencia;
use App\Models\CategoriaIncidencia;
use App\Models\EspacioFisico;
use App\Models\Incidencia;
use App\Models\Prioridad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Incidencias de prueba en cualquier estado. Requiere los catálogos sembrados (CatalogosSeeder).
 *
 * @extends Factory<Incidencia>
 */
class IncidenciaFactory extends Factory
{
    private static int $consecutivo = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo_ticket' => sprintf('TKT-%d-%06d', now()->year, 900000 + ++self::$consecutivo),
            'resumen' => fake()->sentence(4),
            'descripcion' => fake()->paragraph(),
            'canal_radicacion' => 'WEB',
            'espacio_fisico_id' => EspacioFisico::factory(),
            'categoria_id' => fn () => CategoriaIncidencia::query()->where('codigo', 'CERRAJERIA')->value('id'),
            'estado_codigo' => EstadoIncidencia::Registrada,
            'usuario_creador_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Incidencia $incidencia): void {
            $espacio = EspacioFisico::query()->with('piso.edificio')->findOrFail($incidencia->espacio_fisico_id);
            $incidencia->piso_id = $espacio->piso_id;
            $incidencia->edificio_id = $espacio->piso->edificio_id;
            $incidencia->sede_id = $espacio->piso->edificio->sede_id;
        });
    }

    public function enEstado(EstadoIncidencia $estado): static
    {
        return $this->state(fn (array $attributes) => ['estado_codigo' => $estado]);
    }

    public function categoria(string $codigo): static
    {
        return $this->state(fn (array $attributes) => [
            'categoria_id' => CategoriaIncidencia::query()->where('codigo', $codigo)->value('id'),
        ]);
    }

    public function prioridad(string $codigo): static
    {
        return $this->state(fn (array $attributes) => [
            'prioridad_id' => Prioridad::query()->where('codigo', $codigo)->value('id'),
        ]);
    }

    public function asignadaA(User $tecnico): static
    {
        return $this->state(fn (array $attributes) => ['tecnico_asignado_id' => $tecnico->id]);
    }

    public function cerradaHace(int $dias): static
    {
        return $this->state(fn (array $attributes) => [
            'estado_codigo' => EstadoIncidencia::Cerrada,
            'fecha_cierre' => now()->subDays($dias),
        ]);
    }
}
