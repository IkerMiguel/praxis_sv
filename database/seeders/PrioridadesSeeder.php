<?php

namespace Database\Seeders;

use App\Models\MatrizPrioridad;
use App\Models\Prioridad;
use App\Support\MatrizItil;
use Illuminate\Database\Seeder;

class PrioridadesSeeder extends Seeder
{
    /**
     * Prioridades con su SLA en horas hábiles (RN-019) y matriz ITIL Impacto x Urgencia por defecto (HU-11.3).
     */
    public function run(): void
    {
        $prioridades = [
            ['CRITICA', 'Crítica', 4, 1, 6, 'rojo'],
            ['ALTA', 'Alta', 3, 4, 24, 'naranja'],
            ['MEDIA', 'Media', 2, 8, 48, 'amarillo'],
            ['BAJA', 'Baja', 1, 16, 72, 'verde'],
        ];

        foreach ($prioridades as [$codigo, $nombre, $nivel, $respuesta, $resolucion, $color]) {
            Prioridad::updateOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'nivel' => $nivel,
                'horas_primera_respuesta' => $respuesta,
                'horas_resolucion' => $resolucion,
                'color' => $color,
            ]);
        }

        $ids = Prioridad::query()->pluck('id', 'codigo');

        foreach (MatrizItil::POR_DEFECTO as $impacto => $fila) {
            foreach ($fila as $urgencia => $prioridad) {
                MatrizPrioridad::updateOrCreate(
                    ['impacto' => $impacto, 'urgencia' => $urgencia],
                    ['prioridad_id' => $ids[$prioridad]],
                );
            }
        }
    }
}
