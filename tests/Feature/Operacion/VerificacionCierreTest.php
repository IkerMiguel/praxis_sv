<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\Incidencia;
use App\Models\NotificacionPlataforma;
use App\Models\User;
use App\Models\Verificacion;
use App\Services\VerificacionService;
use App\Support\ResultadoCierre;

beforeEach(function () {
    $this->tecnico = User::factory()->tecnico('CERRAJERIA', 'PINTURA')->create();
    $this->verificador = $this->usuarioConRol(Rol::Verificador);
    $this->coordinador = $this->usuarioConRol(Rol::Coordinador);
});

it('aprueba la verificación y solicita confirmación al reportante cuando la categoría lo exige (E12 -> E14)', function () {
    $incidencia = incidenciaPendienteVerificacion($this->tecnico);

    $this->comoRol($this->verificador, Rol::Verificador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/verificar", ['criterios' => [['criterio' => 'Cierra correctamente', 'cumple' => true]]])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E14');

    expect(Verificacion::query()->where('incidencia_id', $incidencia->id)->value('resultado_conforme'))->toBeTrue()
        ->and(NotificacionPlataforma::query()->where('usuario_destino_id', $incidencia->usuario_creador_id)->where('tipo', 'CONFIRMACION_REQUERIDA')->exists())->toBeTrue();
});

it('cierra directamente cuando la categoría no requiere confirmación (E12 -> E15)', function () {
    $incidencia = incidenciaPendienteVerificacion($this->tecnico, 'PINTURA');

    $this->comoRol($this->verificador, Rol::Verificador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/verificar")
        ->assertJsonPath('data.estado.codigo', 'E15');

    expect($incidencia->refresh()->resultado_final_cierre)->toBe(ResultadoCierre::VERIFICADA_SIN_CONFIRMACION);
});

it('impide aprobar con criterios incumplidos o sin evidencias obligatorias', function () {
    $incidencia = incidenciaPendienteVerificacion($this->tecnico);

    $this->comoRol($this->verificador, Rol::Verificador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/verificar", ['criterios' => [['criterio' => 'Cierra correctamente', 'cumple' => false]]])
        ->assertUnprocessable()
        ->assertJsonPath('regla', 'RN-034');

    $incidencia->evidencias()->getQuery()->toBase()->delete();

    $this->comoRol($this->verificador, Rol::Verificador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/verificar")
        ->assertUnprocessable()
        ->assertJsonPath('regla', 'RN-033');
});

it('impide que el técnico ejecutor verifique su propio trabajo de riesgo alto (segregación D03)', function () {
    $multiRol = User::factory()->tecnico('CERRAJERIA')->conRoles(Rol::Verificador)->create();
    $incidencia = incidenciaPendienteVerificacion($multiRol, atributos: ['es_riesgo_inmediato' => true]);

    $this->comoRol($multiRol, Rol::Verificador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/verificar")
        ->assertForbidden()
        ->assertJsonPath('detail', VerificacionService::MENSAJE_SEGREGACION);
});

it('rechaza la intervención con motivo y corrección requerida (E12 -> E13) y alerta al coordinador', function () {
    $incidencia = incidenciaPendienteVerificacion($this->tecnico);

    $this->comoRol($this->verificador, Rol::Verificador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/rechazar-verificacion", ['motivo_rechazo' => 'La filtración de agua persiste'])
        ->assertJsonValidationErrors(['correccion_requerida' => 'Debe especificar la corrección técnica requerida (RN-035).']);

    $this->comoRol($this->verificador, Rol::Verificador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/rechazar-verificacion", ['motivo_rechazo' => 'La filtración de agua persiste', 'correccion_requerida' => 'Reemplazar el empaque y resellar la unión'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E13');

    expect($incidencia->refresh()->numero_reprocesos)->toBe(1)
        ->and(NotificacionPlataforma::query()->where('usuario_destino_id', $this->coordinador->id)->where('tipo', 'VERIFICACION_RECHAZADA')->exists())->toBeTrue();
});

it('reprograma la corrección (E13 -> E10) y bloquea el cierre mientras haya correcciones pendientes (RN-037)', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::EnCorreccion)->asignadaA($this->tecnico)->create();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cierre-administrativo")
        ->assertStatus(409)
        ->assertJsonPath('regla', 'RN-037');

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/planificar-correccion", ['fecha_programada' => now()->addDay()->toDateString(), 'hora_inicio' => '10:00', 'hora_fin' => '12:00'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E10');
});

it('bloquea el cierre con verificaciones técnicas pendientes', function () {
    $incidencia = incidenciaPendienteVerificacion($this->tecnico);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cierre-administrativo")
        ->assertStatus(409)
        ->assertJsonPath('detail', 'No se puede cerrar una incidencia con verificaciones técnicas obligatorias pendientes (RN-037).');
});

it('permite al reportante confirmar con calificación o rechazar la solución (E14 -> E15 / E13)', function () {
    $reportante = $this->usuarioConRol(Rol::Reportante);
    $confirmada = Incidencia::factory()->for($reportante, 'creador')->enEstado(EstadoIncidencia::PendienteConfirmacion)->create();
    $rechazada = Incidencia::factory()->for($reportante, 'creador')->enEstado(EstadoIncidencia::PendienteConfirmacion)->create();

    $this->comoRol($reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$confirmada->id}/confirmar", ['calificacion' => 6])
        ->assertJsonValidationErrors('calificacion');

    $this->comoRol($reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$confirmada->id}/confirmar", ['calificacion' => 5, 'comentario' => 'Reparación concluida satisfactoriamente'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'E15');

    $this->comoRol($reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$rechazada->id}/rechazar-conformidad", ['motivo' => 'El aire acondicionado sigue sin enfriar'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'E13');

    expect($confirmada->refresh()->calificacion_servicio)->toBe(5)
        ->and($confirmada->resultado_final_cierre)->toBe(ResultadoCierre::SOLUCION_CONFIRMADA);
});

it('impide confirmar la solución de otro reportante', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::PendienteConfirmacion)->create();

    $this->comoRol($this->usuarioConRol(Rol::Reportante), Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/confirmar", ['calificacion' => 5])
        ->assertForbidden();
});

it('habilita el cierre administrativo solo tras el plazo de confirmación en días hábiles', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::PendienteConfirmacion)->create(['fecha_ultimo_cambio_estado' => now()]);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cierre-administrativo")
        ->assertUnprocessable()
        ->assertJsonPath('regla', 'HU-9.3');

    $this->travel(12)->days();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cierre-administrativo")
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E15');

    $incidencia->refresh();
    expect($incidencia->es_cierre_administrativo)->toBeTrue()
        ->and($incidencia->resultado_final_cierre)->toBe(ResultadoCierre::CIERRE_ADMINISTRATIVO);
});
