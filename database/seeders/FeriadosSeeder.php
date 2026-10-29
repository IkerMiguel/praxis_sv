<?php

namespace Database\Seeders;

use App\Models\Feriado;
use Illuminate\Database\Seeder;

class FeriadosSeeder extends Seeder
{
    /**
     * Festivos nacionales de Colombia 2026, excluidos del cálculo de horas hábiles.
     */
    public function run(): void
    {
        $feriados = [
            '2026-01-01' => 'Año Nuevo',
            '2026-01-12' => 'Día de los Reyes Magos',
            '2026-03-23' => 'Día de San José',
            '2026-04-02' => 'Jueves Santo',
            '2026-04-03' => 'Viernes Santo',
            '2026-05-01' => 'Día del Trabajo',
            '2026-05-18' => 'Ascensión del Señor',
            '2026-06-08' => 'Corpus Christi',
            '2026-06-15' => 'Sagrado Corazón',
            '2026-06-29' => 'San Pedro y San Pablo',
            '2026-07-20' => 'Día de la Independencia',
            '2026-08-07' => 'Batalla de Boyacá',
            '2026-08-17' => 'La Asunción de la Virgen',
            '2026-10-12' => 'Día de la Diversidad Étnica y Cultural',
            '2026-11-02' => 'Día de Todos los Santos',
            '2026-11-16' => 'Independencia de Cartagena',
            '2026-12-08' => 'Día de la Inmaculada Concepción',
            '2026-12-25' => 'Navidad',
        ];

        foreach ($feriados as $fecha => $descripcion) {
            Feriado::updateOrCreate(['fecha' => $fecha], ['descripcion' => $descripcion]);
        }
    }
}
