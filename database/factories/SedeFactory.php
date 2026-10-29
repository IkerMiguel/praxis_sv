<?php

namespace Database\Factories;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'SEDE-'.fake()->unique()->numerify('####'),
            'nombre' => 'Sede '.fake()->city(),
            'activo' => true,
        ];
    }
}
