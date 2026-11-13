<?php

use App\Enums\CodigoPrioridad;
use App\Enums\Impacto;
use App\Enums\Urgencia;
use App\Support\MatrizItil;

/*
 * Configuración inicial de HU-5.3 / HU-11.3. El escenario de HU-11.3 confirma que Impacto Medio ×
 * Urgencia Media parte en "Baja" y el administrador puede recalibrarla después.
 */
it('calcula la prioridad de cada combinación de impacto y urgencia', function (Impacto $impacto, Urgencia $urgencia, CodigoPrioridad $esperada) {
    expect(MatrizItil::porDefecto($impacto, $urgencia))->toBe($esperada);
})->with([
    'alto × alta' => [Impacto::Alto, Urgencia::Alta, CodigoPrioridad::Critica],
    'alto × media' => [Impacto::Alto, Urgencia::Media, CodigoPrioridad::Alta],
    'alto × baja' => [Impacto::Alto, Urgencia::Baja, CodigoPrioridad::Media],
    'medio × alta' => [Impacto::Medio, Urgencia::Alta, CodigoPrioridad::Alta],
    'medio × media' => [Impacto::Medio, Urgencia::Media, CodigoPrioridad::Baja],
    'medio × baja' => [Impacto::Medio, Urgencia::Baja, CodigoPrioridad::Baja],
    'bajo × alta' => [Impacto::Bajo, Urgencia::Alta, CodigoPrioridad::Media],
    'bajo × media' => [Impacto::Bajo, Urgencia::Media, CodigoPrioridad::Baja],
    'bajo × baja' => [Impacto::Bajo, Urgencia::Baja, CodigoPrioridad::Baja],
]);

it('cubre las 9 combinaciones y solo Alto × Alta resulta crítica', function () {
    $resultados = [];

    foreach (Impacto::cases() as $impacto) {
        foreach (Urgencia::cases() as $urgencia) {
            $resultados[] = MatrizItil::porDefecto($impacto, $urgencia);
        }
    }

    expect($resultados)->toHaveCount(9)
        ->and(array_filter($resultados, fn (CodigoPrioridad $prioridad) => $prioridad === CodigoPrioridad::Critica))->toHaveCount(1);
});

it('nunca baja la prioridad al subir el impacto o la urgencia', function () {
    $nivel = fn (CodigoPrioridad $prioridad): int => array_search($prioridad, [CodigoPrioridad::Baja, CodigoPrioridad::Media, CodigoPrioridad::Alta, CodigoPrioridad::Critica], true);
    $ordenImpacto = [Impacto::Bajo, Impacto::Medio, Impacto::Alto];
    $ordenUrgencia = [Urgencia::Baja, Urgencia::Media, Urgencia::Alta];

    foreach ($ordenImpacto as $i => $impacto) {
        foreach ($ordenUrgencia as $u => $urgencia) {
            $actual = $nivel(MatrizItil::porDefecto($impacto, $urgencia));

            if ($i > 0) {
                expect($actual)->toBeGreaterThanOrEqual($nivel(MatrizItil::porDefecto($ordenImpacto[$i - 1], $urgencia)));
            }

            if ($u > 0) {
                expect($actual)->toBeGreaterThanOrEqual($nivel(MatrizItil::porDefecto($impacto, $ordenUrgencia[$u - 1])));
            }
        }
    }
});
