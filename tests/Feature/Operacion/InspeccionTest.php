<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\Diagnostico;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\User;
use App\Support\ResultadoCierre;

beforeEach(function () {
    $this->tecnico = User::factory()->tecnico('CERRAJERIA')->create();
    $this->coordinador = $this->usuarioConRol(Rol::Coordinador);
});

it('inicia la inspección (E06 -> E07) solo por el técnico asignado', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::PendienteInspeccion)->asignadaA($this->tecnico)->create();
    $otro = User::factory()->tecnico('CERRAJERIA')->create();

    $this->comoRol($otro, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/iniciar-inspeccion")
        ->assertForbidden()
        ->assertJsonPath('detail', 'Solo el técnico responsable asignado puede registrar la inspección o la intervención de esta incidencia.');

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/iniciar-inspeccion")
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E07');

    expect(Diagnostico::query()->where('incidencia_id', $incidencia->id)->value('estado'))->toBe(Diagnostico::EN_INSPECCION);
});

it('exige hallazgos y propuesta de intervención en el diagnóstico (RN-022, RN-023)', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::PendienteInspeccion)->asignadaA($this->tecnico)->create();
    $this->comoRol($this->tecnico, Rol::Tecnico)->postJson("/api/v1/incidencias/{$incidencia->id}/iniciar-inspeccion");

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/diagnostico", ['descripcion_problema' => 'Problema detectado en sitio'])
        ->assertJsonValidationErrors([
            'hallazgos' => 'Los hallazgos de la inspección son obligatorios (RN-022).',
            'intervencion_propuesta' => 'La propuesta de intervención es obligatoria (RN-023).',
        ]);
});

it('devuelve un diagnóstico insuficiente al técnico con observaciones (E07 -> E06)', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/devolver-diagnostico", ['observaciones' => 'Especifique el calibre exacto de la tubería para autorizar la orden'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E06');

    expect(Diagnostico::query()->where('incidencia_id', $incidencia->id)->value('observaciones_devolucion'))
        ->toBe('Especifique el calibre exacto de la tubería para autorizar la orden');
});

it('programa la intervención (E07 -> E10) y conserva la fecha original al reprogramar', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);
    $manana = now()->addDay()->toDateString();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/programar", ['fecha_programada' => $manana, 'hora_inicio' => '08:00', 'hora_fin' => '11:00'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E10')
        ->assertJsonPath('data.plan.fecha_programada', $manana);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->patchJson("/api/v1/incidencias/{$incidencia->id}/reprogramar", ['fecha_programada' => now()->addDays(3)->toDateString(), 'hora_inicio' => '14:00', 'hora_fin' => '16:00', 'motivo' => 'Corte imprevisto del suministro de energía'])
        ->assertOk();

    $evento = EventoAuditoria::query()->where('tipo_evento', 'REPROGRAMACION')->firstOrFail();
    expect($evento->datos['anterior']['fecha_programada'])->toBe($manana)->and($evento->motivo)->toBe('Corte imprevisto del suministro de energía');
});

it('descarta por inspección con evidencia y el coordinador cierra justificadamente (RN-024)', function () {
    $reportante = $this->usuarioConRol(Rol::Reportante);
    $incidencia = Incidencia::factory()->for($reportante, 'creador')->enEstado(EstadoIncidencia::PendienteInspeccion)->asignadaA($this->tecnico)->create();
    $this->comoRol($this->tecnico, Rol::Tecnico)->postJson("/api/v1/incidencias/{$incidencia->id}/iniciar-inspeccion");

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/descartar-por-inspeccion", ['hallazgos' => 'El tomacorriente opera con voltaje adecuado.'])
        ->assertJsonValidationErrors(['justificacion' => 'Debe detallar el motivo por el cual no se confirma la incidencia (RN-024).']);

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/descartar-por-inspeccion", ['hallazgos' => 'El tomacorriente opera con voltaje adecuado.', 'justificacion' => 'Se midieron 120V estables; el elemento funciona correctamente.'])
        ->assertJsonValidationErrors(['evidencias' => 'Debe adjuntar evidencia fotográfica del estado funcional encontrado en sitio (RN-024).']);

    $evidencias = $this->subirEvidencias($this->tecnico, Rol::Tecnico);
    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/descartar-por-inspeccion", ['hallazgos' => 'El tomacorriente opera con voltaje adecuado.', 'justificacion' => 'Se midieron 120V estables; el elemento funciona correctamente.', 'evidencias' => $evidencias])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E07');

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cerrar-no-confirmada")
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E15');

    expect($incidencia->refresh()->resultado_final_cierre)->toBe(ResultadoCierre::NO_CONFIRMADA_EN_INSPECCION);

    $this->comoRol($reportante, Rol::Reportante)
        ->getJson("/api/v1/mis-solicitudes/{$incidencia->id}")
        ->assertJsonPath('data.resultado_cierre.justificacion_tecnica', 'Se midieron 120V estables; el elemento funciona correctamente.')
        ->assertJsonPath('data.evidencias.0.etapa', 'INSPECCION');
});

it('no permite el cierre por no confirmación si el diagnóstico confirmó el daño', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cerrar-no-confirmada")
        ->assertStatus(409)
        ->assertJsonPath('regla', 'RN-024');
});
