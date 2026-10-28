<?php

namespace Database\Factories;

use App\Models\Edificio;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Edificio>
 */
class EdificioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sede_id' => Sede::factory(),
            'codigo' => 'BLQ-'.fake()->unique()->numerify('###'),
            'nombre' => 'Bloque '.fake()->randomLetter(),
            'activo' => true,
        ];
    }
}
