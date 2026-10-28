<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\ActividadMantenimiento;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\NotificacionPlataforma;
use App\Models\PlanIntervencion;
use App\Models\Suspension;
use App\Models\User;

beforeEach(function () {
    $this->tecnico = User::factory()->tecnico('CERRAJERIA')->create();
    $this->coordinador = $this->usuarioConRol(Rol::Coordinador);
});

it('inicia la intervención programada (E10 -> E11) registrando la hora de inicio', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::Programada)->asignadaA($this->tecnico)->create();

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/iniciar-atencion")
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E11');

    expect(ActividadMantenimiento::query()->where('incidencia_id', $incidencia->id)->whereNotNull('fecha_inicio')->exists())->toBeTrue();
});

it('bloquea el envío a verificación sin evidencias obligatorias (RN-033)', function () {
    $incidencia = incidenciaEnAtencion($this->tecnico);

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/registrar-intervencion", ['actividades_realizadas' => 'Se cambió la chapa del aula.', 'tiempo_invertido_minutos' => 30])
        ->assertJsonValidationErrors(['evidencias' => 'Debe adjuntar las evidencias obligatorias del trabajo realizado antes de solicitar la verificación (RN-033)']);

    expect($incidencia->refresh()->estado_codigo)->toBe(EstadoIncidencia::EnAtencion);
});

it('exige documentar las actividades ejecutadas (RN-032)', function () {
    $incidencia = incidenciaEnAtencion($this->tecnico);
    $evidencias = $this->subirEvidencias($this->tecnico, Rol::Tecnico);

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/registrar-intervencion", ['evidencias' => $evidencias])
        ->assertJsonValidationErrors([
            'actividades_realizadas' => 'Debe documentar las actividades ejecutadas (RN-032).',
            'tiempo_invertido_minutos' => 'Debe registrar el tiempo empleado en la intervención (RN-032).',
        ]);
});

it('registra la intervención con materiales y evidencias y pasa a verificación (E11 -> E12)', function () {
    $verificador = $this->usuarioConRol(Rol::Verificador);
    $incidencia = incidenciaEnAtencion($this->tecnico);
    $evidencias = $this->subirEvidencias($this->tecnico, Rol::Tecnico, 2);

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/registrar-intervencion", [
            'actividades_realizadas' => 'Se cambió la chapa y se ajustaron las bisagras.',
            'tiempo_invertido_minutos' => 50,
            'materiales' => [['nombre' => 'Chapa de seguridad', 'cantidad' => 1, 'unidad' => 'und']],
            'evidencias' => $evidencias,
        ])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E12');

    $actividad = ActividadMantenimiento::query()->with('materiales')->where('incidencia_id', $incidencia->id)->firstOrFail();
    expect($actividad->tiempo_invertido_minutos)->toBe(50)
        ->and($actividad->materiales)->toHaveCount(1)
        ->and($incidencia->evidencias()->where('etapa', 'EJECUCION')->count())->toBe(2)
        ->and(NotificacionPlataforma::query()->where('usuario_destino_id', $verificador->id)->exists())->toBeTrue();
});

it('suspende con causa y condiciones de reanudación y luego reanuda conservando la pausa (E11 -> E18 -> E11)', function () {
    $incidencia = incidenciaEnAtencion($this->tecnico);

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/suspender", ['causal' => 'ACCESO_RESTRINGIDO', 'motivo_detallado' => 'Recinto cerrado por jornada de exámenes'])
        ->assertJsonValidationErrors(['condiciones_reanudacion' => 'Debe indicar las condiciones requeridas para reanudar (D07).']);

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/suspender", [
            'causal' => 'ACCESO_RESTRINGIDO',
            'motivo_detallado' => 'Recinto cerrado por jornada de exámenes',
            'condiciones_reanudacion' => 'Habilitar acceso al laboratorio tras finalizar jornada de exámenes',
        ])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E18');

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reanudar", ['estado_destino' => 'E11', 'observacion' => 'Acceso habilitado nuevamente'])
        ->assertForbidden();

    $this->travel(2)->hours();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reanudar", ['estado_destino' => 'E11', 'observacion' => 'Acceso habilitado tras los exámenes'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E11');

    $suspension = Suspension::query()->where('incidencia_id', $incidencia->id)->firstOrFail();
    expect($suspension->estado_origen)->toBe('E11')
        ->and($suspension->estado_destino)->toBe('E11')
        ->and($suspension->fecha_reanudacion)->not->toBeNull()
        ->and(EventoAuditoria::query()->where('tipo_evento', 'REANUDACION')->first()->datos['minutos_suspendida'])->toBeGreaterThanOrEqual(120);
});

it('reanuda hacia programada con una nueva fecha de intervención (E18 -> E10)', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::Programada)->asignadaA($this->tecnico)->create();
    $this->comoRol($this->coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$incidencia->id}/suspender", [
        'causal' => 'FUERZA_MAYOR',
        'motivo_detallado' => 'Corte general de energía en el campus',
        'condiciones_reanudacion' => 'Restablecimiento del suministro eléctrico',
    ])->assertOk();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reanudar", [
            'estado_destino' => 'E10',
            'observacion' => 'Suministro restablecido por la empresa de energía',
            'fecha_programada' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin' => '12:00',
        ])
        ->assertJsonPath('data.estado.codigo', 'E10');

    expect(PlanIntervencion::query()->where('incidencia_id', $incidencia->id)->where('estado_plan', PlanIntervencion::VIGENTE)->count())->toBe(1);
});
