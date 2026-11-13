<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use Illuminate\Database\Seeder;

class EspecialidadesSeeder extends Seeder
{
    public function run(): void
    {
        $especialidades = [
            'ELECTRICIDAD' => 'Electricista',
            'HIDROSANITARIO' => 'Plomero / Hidrosanitario',
            'CERRAJERIA' => 'Cerrajero',
            'OBRA_CIVIL' => 'Obra civil y mampostería',
            'CLIMATIZACION' => 'Técnico de climatización',
            'CARPINTERIA' => 'Carpintero',
            'PINTURA' => 'Pintor',
            'GENERAL' => 'Mantenimiento general',
        ];

        foreach ($especialidades as $codigo => $nombre) {
            Especialidad::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
