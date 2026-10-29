<?php

use App\Support\JornadaHabil;
use Carbon\CarbonImmutable;

/**
 * Jornada 8:00-18:00 en Bogotá. El lunes 2 de noviembre de 2026 es feriado (Todos los Santos).
 */
function jornadaBogota(): JornadaHabil
{
    return new JornadaHabil(8, 18, ['2026-11-02'], 'America/Bogota');
}

function bogota(string $fecha): CarbonImmutable
{
    return CarbonImmutable::parse($fecha, 'America/Bogota');
}

it('suma horas dentro de la misma jornada y devuelve la fecha en UTC', function () {
    $resultado = jornadaBogota()->sumarHoras(bogota('2026-10-28 09:00'), 2);

    expect($resultado->getTimezone()->getName())->toBe('UTC')
        ->and($resultado->toIso8601String())->toBe('2026-10-28T16:00:00+00:00')
        ->and($resultado->setTimezone('America/Bogota')->format('Y-m-d H:i'))->toBe('2026-10-28 11:00');
});

it('continúa al día hábil siguiente cuando se acaba la jornada', function () {
    $resultado = jornadaBogota()->sumarHoras(bogota('2026-10-28 17:00'), 2);

    expect($resultado->setTimezone('America/Bogota')->format('Y-m-d H:i'))->toBe('2026-10-29 09:00');
});

it('salta el fin de semana y el feriado', function () {
    $resultado = jornadaBogota()->sumarHoras(bogota('2026-10-30 17:00'), 2);

    expect($resultado->setTimezone('America/Bogota')->format('l Y-m-d H:i'))->toBe('Tuesday 2026-11-03 09:00');
});

it('empieza a contar en la apertura cuando el instante cae fuera de horario', function (string $inicio, string $esperado) {
    expect(jornadaBogota()->sumarHoras(bogota($inicio), 1)->setTimezone('America/Bogota')->format('Y-m-d H:i'))->toBe($esperado);
})->with([
    'madrugada' => ['2026-10-28 06:00', '2026-10-28 09:00'],
    'después del cierre' => ['2026-10-28 19:00', '2026-10-29 09:00'],
    'justo al cierre' => ['2026-10-28 18:00', '2026-10-29 09:00'],
    'sábado' => ['2026-10-31 10:00', '2026-11-03 09:00'],
]);

it('termina exactamente al cierre si las horas alcanzan justo', function () {
    expect(jornadaBogota()->sumarHoras(bogota('2026-10-28 08:00'), 10)->setTimezone('America/Bogota')->format('Y-m-d H:i'))
        ->toBe('2026-10-28 18:00');
});

it('acepta fracciones de hora redondeando a minutos', function () {
    expect(jornadaBogota()->sumarHoras(bogota('2026-10-28 08:00'), 1.5)->setTimezone('America/Bogota')->format('H:i'))->toBe('09:30');
});

it('suma días hábiles completos según la duración de la jornada', function () {
    $resultado = jornadaBogota()->sumarDias(bogota('2026-10-28 10:00'), 5);

    expect($resultado->setTimezone('America/Bogota')->format('l Y-m-d H:i'))->toBe('Thursday 2026-11-05 10:00');
});

it('cuenta solo los minutos hábiles entre dos instantes', function () {
    $jornada = jornadaBogota();

    expect($jornada->minutosEntre(bogota('2026-10-30 17:00'), bogota('2026-11-03 09:00')))->toBe(120)
        ->and($jornada->minutosEntre(bogota('2026-10-28 07:00'), bogota('2026-10-28 19:00')))->toBe(600)
        ->and($jornada->minutosEntre(bogota('2026-10-28 12:00'), bogota('2026-10-28 12:00')))->toBe(0)
        ->and($jornada->minutosEntre(bogota('2026-10-29 10:00'), bogota('2026-10-28 10:00')))->toBe(0);
});

it('reconoce fines de semana y feriados como días no hábiles', function (string $dia, bool $habil) {
    expect(jornadaBogota()->esDiaHabil(bogota($dia)))->toBe($habil);
})->with([
    'miércoles' => ['2026-10-28 12:00', true],
    'sábado' => ['2026-10-31 12:00', false],
    'domingo' => ['2026-11-01 12:00', false],
    'feriado' => ['2026-11-02 12:00', false],
    'martes' => ['2026-11-03 12:00', true],
]);

it('evalúa el día hábil en la hora local aunque la fecha llegue en UTC', function () {
    $sabadoUtcViernesBogota = CarbonImmutable::parse('2026-10-31 02:00', 'UTC');

    expect(jornadaBogota()->esDiaHabil($sabadoUtcViernesBogota))->toBeTrue();
});
