<?php

namespace Database\Factories;

use App\Enums\Rol as RolCodigo;
use App\Models\Especialidad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'activo' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    /**
     * Asigna uno o varios roles del catálogo (requiere RolesSeeder).
     */
    public function conRoles(RolCodigo ...$roles): static
    {
        return $this->afterCreating(function (User $usuario) use ($roles): void {
            $ids = Rol::query()->whereIn('codigo', array_map(fn (RolCodigo $rol) => $rol->value, $roles))->pluck('id');
            $usuario->roles()->syncWithoutDetaching($ids->mapWithKeys(fn (int $id) => [$id => ['fecha_asignacion' => now()]])->all());
        });
    }

    /**
     * Técnico (R04) con las especialidades indicadas por código.
     */
    public function tecnico(string ...$especialidades): static
    {
        return $this->conRoles(RolCodigo::Tecnico)->afterCreating(function (User $usuario) use ($especialidades): void {
            $usuario->especialidades()->syncWithoutDetaching(Especialidad::query()->whereIn('codigo', $especialidades)->pluck('id')->all());
        });
    }
}
