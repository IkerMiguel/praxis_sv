<?php

use App\Enums\EstadoIncidencia as E;
use App\Services\MaquinaEstados;

it('permite las transiciones de la matriz D07 con su rol autorizado', function (E $origen, E $destino, string $rol) {
    expect(MaquinaEstados::admite($origen, $destino, $rol))->toBeTrue()
        ->and(MaquinaEstados::admite($origen, $destino, 'R07'))->toBeTrue();
})->with([
    'inicia revisión' => [E::Registrada, E::EnRevision, 'R02'],
    'falta información' => [E::EnRevision, E::PendienteInformacion, 'R02'],
    'reportante responde' => [E::PendienteInformacion, E::EnRevision, 'R01'],
    'información completa' => [E::EnRevision, E::EnClasificacion, 'R02'],
    'no procedente' => [E::EnRevision, E::NoProcedente, 'R02'],
    'validación finalizada' => [E::EnClasificacion, E::PendienteAsignacion, 'R02'],
    'asigna responsable' => [E::PendienteAsignacion, E::PendienteInspeccion, 'R03'],
    'comienza inspección' => [E::PendienteInspeccion, E::EnDiagnostico, 'R04'],
    'requiere autorización' => [E::EnDiagnostico, E::PendienteAutorizacion, 'R03'],
    'faltan recursos' => [E::EnDiagnostico, E::PendienteRecursos, 'R03'],
    'intervención viable' => [E::EnDiagnostico, E::Programada, 'R03'],
    'aprobada con recursos' => [E::PendienteAutorizacion, E::Programada, 'R05'],
    'aprobada sin recursos' => [E::PendienteAutorizacion, E::PendienteRecursos, 'R05'],
    'recursos disponibles' => [E::PendienteRecursos, E::Programada, 'R03'],
    'inicia intervención' => [E::Programada, E::EnAtencion, 'R04'],
    'trabajo registrado' => [E::EnAtencion, E::PendienteVerificacion, 'R04'],
    'no conforme' => [E::PendienteVerificacion, E::EnCorreccion, 'R06'],
    'planifica corrección' => [E::EnCorreccion, E::Programada, 'R03'],
    'conforme con confirmación' => [E::PendienteVerificacion, E::PendienteConfirmacion, 'R06'],
    'conforme sin confirmación' => [E::PendienteVerificacion, E::Cerrada, 'R06'],
    'reportante confirma' => [E::PendienteConfirmacion, E::Cerrada, 'R01'],
    'reportante rechaza' => [E::PendienteConfirmacion, E::EnCorreccion, 'R01'],
]);

it('admite las bifurcaciones que agregan las historias de usuario', function (E $origen, E $destino, string $rol) {
    expect(MaquinaEstados::admite($origen, $destino, $rol))->toBeTrue();
})->with([
    'caducidad de aclaración (sistema)' => [E::PendienteInformacion, E::Cancelada, MaquinaEstados::SISTEMA],
    'cancelación del reportante' => [E::Registrada, E::Cancelada, 'R01'],
    'duplicado desde revisión' => [E::EnRevision, E::Cerrada, 'R02'],
    'devolución del diagnóstico' => [E::EnDiagnostico, E::PendienteInspeccion, 'R03'],
    'cierre no confirmada' => [E::EnDiagnostico, E::Cerrada, 'R03'],
    'rechazo con devolución' => [E::PendienteAutorizacion, E::EnDiagnostico, 'R05'],
    'suspensión por el técnico' => [E::EnAtencion, E::Suspendida, 'R04'],
    'reanudación' => [E::Suspendida, E::EnAtencion, 'R03'],
    'cierre administrativo' => [E::PendienteConfirmacion, E::Cerrada, 'R03'],
    'reapertura con intervención' => [E::Cerrada, E::EnCorreccion, 'R01'],
    'reapertura sin intervención' => [E::Cerrada, E::PendienteAsignacion, 'R01'],
]);

it('rechaza transiciones que no están en la matriz', function (E $origen, E $destino) {
    expect(MaquinaEstados::admite($origen, $destino, 'R07'))->toBeFalse();
})->with([
    'saltar de registrada a programada' => [E::Registrada, E::Programada],
    'asignar sin clasificar' => [E::EnRevision, E::PendienteInspeccion],
    'atender sin programar' => [E::EnDiagnostico, E::EnAtencion],
    'cerrar en atención (RN-037)' => [E::EnAtencion, E::Cerrada],
    'cerrar en corrección (RN-037)' => [E::EnCorreccion, E::Cerrada],
    'verificar sin ejecutar' => [E::Programada, E::PendienteVerificacion],
    'reabrir hacia programada' => [E::Cerrada, E::Programada],
    'misma transición repetida' => [E::EnRevision, E::EnRevision],
]);

it('rechaza transiciones válidas hechas con un rol no autorizado', function (E $origen, E $destino, ?string $rol) {
    expect(MaquinaEstados::admite($origen, $destino, $rol))->toBeFalse();
})->with([
    'técnico asigna' => [E::PendienteAsignacion, E::PendienteInspeccion, 'R04'],
    'coordinador clasifica' => [E::EnClasificacion, E::PendienteAsignacion, 'R03'],
    'técnico verifica su trabajo' => [E::PendienteVerificacion, E::Cerrada, 'R04'],
    'gestor programa' => [E::EnDiagnostico, E::Programada, 'R02'],
    'reportante inicia revisión' => [E::Registrada, E::EnRevision, 'R01'],
    'verificador confirma por el reportante' => [E::PendienteConfirmacion, E::Cerrada, 'R06'],
    'sin rol activo' => [E::Registrada, E::EnRevision, null],
]);

it('no tiene salidas desde los estados terminales No procedente y Cancelada', function (E $estado) {
    expect($estado->esTerminal())->toBeTrue()
        ->and(MaquinaEstados::matriz())->not->toHaveKey($estado->value);

    foreach (E::cases() as $destino) {
        expect(MaquinaEstados::admite($estado, $destino, 'R07'))->toBeFalse();
    }
})->with([E::NoProcedente, E::Cancelada]);

it('solo permite salir de Cerrada por reapertura del reportante', function () {
    expect(E::Cerrada->esTerminal())->toBeTrue()
        ->and(MaquinaEstados::destinosDesde(E::Cerrada, 'R01'))->toBe(['E13', 'E05'])
        ->and(MaquinaEstados::destinosDesde(E::Cerrada, 'R03'))->toBe([]);
});

it('marca como terminales solo E15, E16 y E17', function () {
    $terminales = array_values(array_filter(E::cases(), fn (E $estado) => $estado->esTerminal()));

    expect($terminales)->toBe([E::Cerrada, E::NoProcedente, E::Cancelada]);
});

it('nunca permite volver a Registrada y solo usa estados existentes', function () {
    foreach (MaquinaEstados::matriz() as $origen => $destinos) {
        expect(E::tryFrom($origen))->not->toBeNull();

        foreach (array_keys($destinos) as $destino) {
            expect(E::tryFrom($destino))->not->toBeNull()
                ->and($destino)->not->toBe(E::Registrada->value);
        }
    }
});

it('lista los destinos disponibles según el rol', function () {
    expect(MaquinaEstados::destinosDesde(E::EnDiagnostico, 'R04'))->toBe(['E18'])
        ->and(MaquinaEstados::destinosDesde(E::EnDiagnostico, 'R03'))->toBe(['E06', 'E08', 'E09', 'E10', 'E15', 'E18'])
        ->and(MaquinaEstados::destinosDesde(E::Registrada, null))->toBe([]);
});
