<?php

namespace Database\Factories;

use App\Models\EspacioFisico;
use App\Models\Piso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EspacioFisico>
 */
class EspacioFisicoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'piso_id' => Piso::factory(),
            'codigo' => 'ESP-'.fake()->unique()->numerify('####'),
            'nombre' => 'Aula '.fake()->numerify('###'),
            'tipo' => 'AULA',
            'activo' => true,
        ];
    }
}
