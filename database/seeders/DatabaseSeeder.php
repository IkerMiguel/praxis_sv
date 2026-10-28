<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Catálogos del proceso más datos de demostración (ubicaciones y un usuario por rol).
     */
    public function run(): void
    {
        $this->call([
            CatalogosSeeder::class,
            UbicacionesSeeder::class,
            UsuariosSeeder::class,
        ]);
    }
}
