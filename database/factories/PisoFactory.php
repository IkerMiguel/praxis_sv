<?php

namespace Database\Factories;

use App\Models\Edificio;
use App\Models\Piso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Piso>
 */
class PisoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nivel = fake()->numberBetween(1, 9);

        return [
            'edificio_id' => Edificio::factory(),
            'codigo' => 'P'.fake()->unique()->numerify('###'),
            'nombre' => "Piso {$nivel}",
            'nivel' => $nivel,
            'activo' => true,
        ];
    }
}
