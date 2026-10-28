<?php

namespace Database\Seeders;

use App\Models\CategoriaIncidencia;
use App\Models\Especialidad;
use Illuminate\Database\Seeder;

class CategoriasSeeder extends Seeder
{
    /**
     * Categorías de daño con sus interruptores de negocio (HU-11.2). Las categorías de riesgo
     * estructural e hidráulico mayor exigen evidencia fotográfica (RN-005).
     */
    public function run(): void
    {
        $categorias = [
            ['ELECTRICA', 'Eléctrica', 'ELECTRICIDAD', false, true, 'MEDIO', ['Luminarias', 'Tomacorrientes', 'Interruptores', 'Cableado expuesto']],
            ['HIDROSANITARIA', 'Hidrosanitaria', 'HIDROSANITARIO', false, true, 'BAJO', ['Lavamanos', 'Sanitarios', 'Grifería', 'Desagües']],
            ['FUGA_HIDRAULICA_MAYOR', 'Fuga Hidráulica Mayor', 'HIDROSANITARIO', true, true, 'ALTO', ['Tubería rota', 'Inundación']],
            ['ESTRUCTURAL_TECHOS', 'Daño Estructural / Techos', 'OBRA_CIVIL', true, true, 'ALTO', ['Cielo raso', 'Filtraciones en cubierta', 'Grietas estructurales']],
            ['MAMPOSTERIA', 'Mampostería', 'OBRA_CIVIL', true, true, 'MEDIO', ['Muros', 'Pisos', 'Escaleras']],
            ['CERRAJERIA', 'Cerrajería', 'CERRAJERIA', false, true, 'BAJO', ['Chapas', 'Puertas', 'Llaves']],
            ['CLIMATIZACION', 'Aire Acondicionado y Climatización', 'CLIMATIZACION', false, true, 'BAJO', ['Aire acondicionado', 'Ventilación']],
            ['MOBILIARIO', 'Mobiliario', 'CARPINTERIA', false, true, 'BAJO', ['Sillas', 'Mesas', 'Tableros']],
            ['PINTURA', 'Pintura y acabados', 'PINTURA', false, false, 'BAJO', ['Paredes', 'Techos']],
            ['CUARTO_TECNICO', 'Mantenimiento de cuartos técnicos', 'GENERAL', false, false, 'MEDIO', ['Subestación', 'Cuarto de bombas']],
        ];

        $especialidades = Especialidad::query()->pluck('id', 'codigo');

        foreach ($categorias as [$codigo, $nombre, $especialidad, $exigeEvidencia, $requiereConfirmacion, $riesgo, $subcategorias]) {
            $principal = CategoriaIncidencia::updateOrCreate(['codigo' => $codigo], [
                'categoria_padre_id' => null,
                'nombre' => $nombre,
                'exige_evidencia_fotografica' => $exigeEvidencia,
                'requiere_confirmacion_cierre' => $requiereConfirmacion,
                'nivel_riesgo_base' => $riesgo,
                'especialidad_id' => $especialidades[$especialidad] ?? null,
                'activo' => true,
            ]);

            foreach ($subcategorias as $indice => $subcategoria) {
                CategoriaIncidencia::updateOrCreate(['codigo' => $codigo.'_'.($indice + 1)], [
                    'categoria_padre_id' => $principal->id,
                    'nombre' => $subcategoria,
                    'exige_evidencia_fotografica' => $exigeEvidencia,
                    'requiere_confirmacion_cierre' => $requiereConfirmacion,
                    'nivel_riesgo_base' => $riesgo,
                    'especialidad_id' => $principal->especialidad_id,
                    'activo' => true,
                ]);
            }
        }
    }
}
