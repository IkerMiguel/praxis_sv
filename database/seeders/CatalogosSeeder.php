<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Catálogos mínimos para operar la plataforma (seguro para cualquier ambiente).
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            EspecialidadesSeeder::class,
            EstadosSeeder::class,
            CategoriasSeeder::class,
            PrioridadesSeeder::class,
            ParametrosSeeder::class,
            FeriadosSeeder::class,
            ModulosPermisosSeeder::class,
        ]);
    }
}
