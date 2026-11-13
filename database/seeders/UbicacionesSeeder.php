<?php

namespace Database\Seeders;

use App\Models\Edificio;
use App\Models\EspacioFisico;
use App\Models\Piso;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class UbicacionesSeeder extends Seeder
{
    /**
     * Ubicaciones de ejemplo con la jerarquía de 4 niveles (RN-003).
     */
    public function run(): void
    {
        $sedes = [
            ['SEDE-PRINCIPAL', 'Campus Principal', 'Calle 17 # 4-81', [
                ['BLOQUE-A', 'Bloque A', [
                    ['P1', 'Piso 1', 1, [['A-101', 'Aula 101', 'AULA'], ['A-102', 'Aula 102', 'AULA'], ['A-BANO-1', 'Baño Piso 1', 'BANO']]],
                    ['P2', 'Piso 2', 2, [['A-201', 'Aula 201', 'AULA'], ['A-202', 'Sala de profesores', 'OFICINA']]],
                    ['P3', 'Piso 3', 3, [['LAB-302', 'Laboratorio 302', 'LABORATORIO'], ['LAB-305', 'Laboratorio 305', 'LABORATORIO']]],
                ]],
                ['BLOQUE-B', 'Bloque B', [
                    ['P1', 'Piso 1', 1, [['B-101', 'Auditorio', 'AUDITORIO'], ['B-CT', 'Cuarto técnico', 'CUARTO_TECNICO']]],
                    ['P2', 'Piso 2', 2, [['LAB-QUIM-201', 'Laboratorio de Química 201', 'LABORATORIO']]],
                ]],
            ]],
            ['SEDE-NORTE', 'Sede Norte', 'Avenida Carrera 45 # 103-60', [
                ['BLOQUE-C', 'Bloque C', [
                    ['P1', 'Piso 1', 1, [['C-101', 'Aula 101', 'AULA']]],
                    ['P2', 'Piso 2', 2, [['LAB-FIS-204', 'Laboratorio de Física 204', 'LABORATORIO']]],
                ]],
            ]],
        ];

        foreach ($sedes as [$codigoSede, $nombreSede, $direccion, $edificios]) {
            $sede = Sede::updateOrCreate(['codigo' => $codigoSede], ['nombre' => $nombreSede, 'direccion' => $direccion, 'activo' => true]);

            foreach ($edificios as [$codigoEdificio, $nombreEdificio, $pisos]) {
                $edificio = Edificio::updateOrCreate(
                    ['sede_id' => $sede->id, 'codigo' => $codigoEdificio],
                    ['nombre' => $nombreEdificio, 'activo' => true],
                );

                foreach ($pisos as [$codigoPiso, $nombrePiso, $nivel, $espacios]) {
                    $piso = Piso::updateOrCreate(
                        ['edificio_id' => $edificio->id, 'codigo' => $codigoPiso],
                        ['nombre' => $nombrePiso, 'nivel' => $nivel, 'activo' => true],
                    );

                    foreach ($espacios as [$codigoEspacio, $nombreEspacio, $tipo]) {
                        EspacioFisico::updateOrCreate(
                            ['piso_id' => $piso->id, 'codigo' => $codigoEspacio],
                            ['nombre' => $nombreEspacio, 'tipo' => $tipo, 'activo' => true],
                        );
                    }
                }
            }
        }
    }
}
