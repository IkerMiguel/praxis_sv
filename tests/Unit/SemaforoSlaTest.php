<?php

use App\Enums\EstadoSla;

it('clasifica el plazo según el porcentaje consumido con umbral del 75 %', function (float $porcentaje, EstadoSla $esperado, string $color) {
    $estado = EstadoSla::segunConsumo($porcentaje, 75);

    expect($estado)->toBe($esperado)
        ->and($estado->color())->toBe($color);
})->with([
    'recién creada' => [0.0, EstadoSla::EnPlazo, 'verde'],
    'justo antes del umbral' => [74.9, EstadoSla::EnPlazo, 'verde'],
    'en el umbral' => [75.0, EstadoSla::ProximaAVencer, 'amarillo'],
    'casi vencida' => [99.9, EstadoSla::ProximaAVencer, 'amarillo'],
    'exactamente al 100 %' => [100.0, EstadoSla::ProximaAVencer, 'amarillo'],
    'pasada del 100 %' => [100.1, EstadoSla::Vencida, 'rojo'],
    'muy vencida' => [250.0, EstadoSla::Vencida, 'rojo'],
]);

it('respeta un umbral parametrizado distinto', function () {
    expect(EstadoSla::segunConsumo(60.0, 50))->toBe(EstadoSla::ProximaAVencer)
        ->and(EstadoSla::segunConsumo(49.9, 50))->toBe(EstadoSla::EnPlazo);
});

it('asigna color a los estados congelados y sin plazo', function () {
    expect(EstadoSla::Cumplida->color())->toBe('verde')
        ->and(EstadoSla::Incumplida->color())->toBe('rojo')
        ->and(EstadoSla::SinPlazo->color())->toBe('gris');
});
