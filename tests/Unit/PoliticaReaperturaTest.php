<?php

use App\Enums\EstadoIncidencia;
use App\Support\PoliticaReapertura;
use App\Support\ResultadoCierre;
use Carbon\CarbonImmutable;

/**
 * Evalúa una incidencia cerrada el 28/10/2026 con ventana de garantía hasta el 04/11/2026.
 *
 * @return array{permitido: bool, motivo: string|null, fecha_limite: string|null, reaperturas_restantes: int}
 */
function evaluarReapertura(
    EstadoIncidencia $estado = EstadoIncidencia::Cerrada,
    ?string $resultado = ResultadoCierre::SOLUCION_CONFIRMADA,
    int $reaperturas = 0,
    int $maximo = 2,
    string $ahora = '2026-10-30 10:00',
    bool $conCierre = true,
): array {
    return PoliticaReapertura::evaluar(
        $estado,
        $conCierre ? CarbonImmutable::parse('2026-10-28 15:00') : null,
        $resultado,
        $reaperturas,
        $maximo,
        $conCierre ? CarbonImmutable::parse('2026-11-04 15:00') : null,
        CarbonImmutable::parse($ahora),
    );
}

it('permite reabrir dentro de la ventana y con cupo', function () {
    expect(evaluarReapertura())->toBe([
        'permitido' => true,
        'motivo' => null,
        'fecha_limite' => '2026-11-04T15:00:00+00:00',
        'reaperturas_restantes' => 2,
    ]);
});

it('permite reabrir justo en el límite de la ventana', function () {
    expect(evaluarReapertura(ahora: '2026-11-04 15:00')['permitido'])->toBeTrue();
});

it('rechaza la reapertura de incidencias que no están cerradas', function (EstadoIncidencia $estado) {
    $resultado = evaluarReapertura(estado: $estado);

    expect($resultado['permitido'])->toBeFalse()
        ->and($resultado['motivo'])->toBe('Solo se pueden reabrir incidencias cerradas.');
})->with([EstadoIncidencia::PendienteConfirmacion, EstadoIncidencia::Cancelada, EstadoIncidencia::NoProcedente]);

it('rechaza la reapertura si no hay fecha de cierre', function () {
    expect(evaluarReapertura(conCierre: false)['permitido'])->toBeFalse();
});

it('no permite reabrir una solicitud unificada por duplicidad', function () {
    expect(evaluarReapertura(resultado: ResultadoCierre::VINCULADA_POR_DUPLICIDAD))->toBe([
        'permitido' => false,
        'motivo' => 'La solicitud fue unificada con la incidencia principal; consulte su avance.',
        'fecha_limite' => null,
        'reaperturas_restantes' => 0,
    ]);
});

it('rechaza la reapertura fuera de la ventana de garantía', function () {
    $resultado = evaluarReapertura(ahora: '2026-11-04 15:01');

    expect($resultado['permitido'])->toBeFalse()
        ->and($resultado['motivo'])->toBe('La ventana de garantía para reabrir la solicitud ya expiró.')
        ->and($resultado['reaperturas_restantes'])->toBe(2);
});

it('rechaza la reapertura cuando se agotó el cupo', function () {
    $resultado = evaluarReapertura(reaperturas: 2);

    expect($resultado['permitido'])->toBeFalse()
        ->and($resultado['motivo'])->toBe('La solicitud alcanzó el máximo de 2 reaperturas permitidas.')
        ->and($resultado['reaperturas_restantes'])->toBe(0);
});

it('descuenta las reaperturas ya usadas', function () {
    expect(evaluarReapertura(reaperturas: 1)['reaperturas_restantes'])->toBe(1)
        ->and(evaluarReapertura(reaperturas: 5)['reaperturas_restantes'])->toBe(0);
});
