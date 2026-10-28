<?php

namespace Tests;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Siembra los catálogos del proceso (roles, estados, categorías, matriz ITIL, parámetros...) en cada prueba.
     */
    protected bool $seed = true;

    protected string $seeder = CatalogosSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('evidencias');
    }

    /**
     * Autentica al usuario con Sanctum y fija el rol activo (cabecera X-Active-Role).
     */
    protected function comoRol(User $usuario, Rol $rol): static
    {
        Sanctum::actingAs($usuario);

        return $this->withHeader('X-Active-Role', $rol->value);
    }

    protected function usuarioConRol(Rol ...$roles): User
    {
        return User::factory()->conRoles(...$roles)->create();
    }
}
