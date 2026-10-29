<?php

namespace Database\Seeders;

use App\Enums\EstadoIncidencia;
use App\Models\Estado;
use Illuminate\Database\Seeder;

class EstadosSeeder extends Seeder
{
    /**
     * Glosario oficial de estados (D07) con su significado para el reportante (HU-2.4).
     */
    public function run(): void
    {
        $definiciones = [
            'E01' => ['ABIERTO', 'Tu reporte fue recibido y espera la revisión inicial de la mesa de ayuda.', false, 'azul'],
            'E02' => ['EN_CURSO', 'Necesitamos más información o fotos para entender el daño. Debes responder la aclaración.', true, 'naranja'],
            'E03' => ['EN_CURSO', 'La mesa de ayuda está revisando si el daño corresponde a planta física y si ya fue reportado.', false, 'azul'],
            'E04' => ['EN_CURSO', 'Se está definiendo la categoría y la prioridad de atención.', false, 'azul'],
            'E05' => ['ABIERTO', 'Tu reporte fue validado y espera la asignación de un técnico.', false, 'violeta'],
            'E06' => ['EN_CURSO', 'Un técnico fue asignado y realizará la inspección en sitio.', false, 'violeta'],
            'E07' => ['EN_CURSO', 'El técnico está determinando la causa del daño y lo que se necesita para repararlo.', false, 'violeta'],
            'E08' => ['EN_CURSO', 'La reparación requiere una autorización administrativa o presupuestal.', false, 'amarillo'],
            'E09' => ['EN_CURSO', 'Se están consiguiendo los materiales o repuestos necesarios.', false, 'amarillo'],
            'E10' => ['ABIERTO', 'La intervención tiene fecha y cuadrilla programadas.', false, 'cian'],
            'E11' => ['EN_CURSO', 'El personal técnico está ejecutando la reparación.', false, 'cian'],
            'E12' => ['EN_CURSO', 'El trabajo terminó y está pendiente de la verificación de calidad.', false, 'cian'],
            'E13' => ['EN_CURSO', 'La verificación encontró defectos y el trabajo será corregido.', false, 'rojo'],
            'E14' => ['EN_CURSO', 'El trabajo fue verificado. Debes confirmar si el problema quedó resuelto.', true, 'naranja'],
            'E15' => ['TERMINAL', 'La incidencia fue atendida y cerrada.', false, 'verde'],
            'E16' => ['TERMINAL', 'El reporte no corresponde a infraestructura física y fue descartado.', false, 'gris'],
            'E17' => ['TERMINAL', 'La solicitud fue cancelada con causa justificada.', false, 'gris'],
            'E18' => ['EN_CURSO', 'La atención está detenida temporalmente por una causa documentada.', false, 'amarillo'],
        ];

        foreach (EstadoIncidencia::cases() as $indice => $estado) {
            [$categoria, $significado, $requiereAccion, $color] = $definiciones[$estado->value];

            Estado::updateOrCreate(['codigo' => $estado->value], [
                'nombre' => $estado->nombre(),
                'categoria' => $categoria,
                'significado_publico' => $significado,
                'requiere_accion_reportante' => $requiereAccion,
                'es_terminal' => $estado->esTerminal(),
                'color' => $color,
                'orden' => $indice + 1,
            ]);
        }
    }
}
