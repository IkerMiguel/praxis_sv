<?php

namespace Tests;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
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

    /**
     * Imagen PNG real (cabecera válida) para probar la detección por magic bytes.
     */
    protected function imagenPng(string $nombre = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }

    /**
     * Sube evidencias pendientes de vincular y devuelve sus identificadores.
     *
     * @return list<int>
     */
    protected function subirEvidencias(User $usuario, Rol $rol, int $cantidad = 1, array $extra = []): array
    {
        $archivos = array_map(fn (int $indice) => $this->imagenPng("foto{$indice}.png"), range(1, $cantidad));

        return $this->comoRol($usuario, $rol)
            ->post('/api/v1/incidencias/evidencias/upload', ['archivos' => $archivos] + $extra, ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.*.id');
    }
}
