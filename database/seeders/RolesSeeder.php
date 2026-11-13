<?php

namespace Database\Seeders;

use App\Enums\Rol as RolCodigo;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Catálogo oficial de los 7 roles (D03).
     */
    public function run(): void
    {
        $descripciones = [
            'R01' => 'Comunidad universitaria: registra incidencias, responde aclaraciones y confirma la conformidad del cierre.',
            'R02' => 'Mesa de ayuda de infraestructura: revisa reportes, solicita información, identifica duplicados y clasifica.',
            'R03' => 'Jefe operativo: coordina inspecciones, evalúa diagnósticos, planifica y asigna personal según capacidad.',
            'R04' => 'Personal de campo: inspecciona, diagnostica, ejecuta reparaciones y documenta evidencias.',
            'R05' => 'Ordenador del gasto: autoriza compras de materiales extraordinarios o contratación externa.',
            'R06' => 'Supervisor de calidad independiente: verifica resultados técnicos y acepta o rechaza trabajos.',
            'R07' => 'Gestiona usuarios, permisos, ubicaciones, categorías y reglas del sistema.',
        ];

        foreach (RolCodigo::cases() as $indice => $rol) {
            Rol::updateOrCreate(['codigo' => $rol->value], [
                'nombre' => $rol->nombre(),
                'descripcion' => $descripciones[$rol->value],
                'ruta_inicio' => $rol->rutaInicio(),
                'orden' => $indice + 1,
            ]);
        }
    }
}
