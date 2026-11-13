<?php

use App\Services\GeneradorCodigoTicket;

it('arma el código TKT-AAAA-###### con el consecutivo relleno a seis dígitos', function (int $anio, int $consecutivo, string $esperado) {
    expect(GeneradorCodigoTicket::formatear($anio, $consecutivo))->toBe($esperado)
        ->and(GeneradorCodigoTicket::esValido($esperado))->toBeTrue();
})->with([
    'primero del año' => [2026, 1, 'TKT-2026-000001'],
    'centenas' => [2026, 345, 'TKT-2026-000345'],
    'último posible' => [2026, 999999, 'TKT-2026-999999'],
    'otro año' => [2027, 12, 'TKT-2027-000012'],
]);

it('genera consecutivos ordenados y sin repetir', function () {
    $codigos = array_map(fn (int $numero) => GeneradorCodigoTicket::formatear(2026, $numero), range(1, 50));

    expect(array_unique($codigos))->toHaveCount(50)
        ->and($codigos)->toBe(collect($codigos)->sort()->values()->all())
        ->and($codigos[9])->toBe('TKT-2026-000010');
});

it('rechaza códigos con formato inválido', function (string $codigo) {
    expect(GeneradorCodigoTicket::esValido($codigo))->toBeFalse();
})->with([
    'sin relleno' => ['TKT-2026-1'],
    'año de dos dígitos' => ['TKT-26-000001'],
    'minúsculas' => ['tkt-2026-000001'],
    'siete dígitos' => ['TKT-2026-1000000'],
    'prefijo distinto' => ['TCK-2026-000001'],
    'con espacios' => [' TKT-2026-000001'],
    'formato de la RN-001 antigua' => ['#TKT-2026-0001'],
]);

it('produce un código fuera de formato si el consecutivo supera 999999, que el CHECK de la BD rechaza', function () {
    expect(GeneradorCodigoTicket::esValido(GeneradorCodigoTicket::formatear(2026, 1000000)))->toBeFalse();
});
