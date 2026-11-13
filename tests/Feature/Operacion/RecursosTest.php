<?php

use App\Enums\EstadoIncidencia;
use App\Enums\EstadoSolicitudRecursos;
use App\Enums\Rol;
use App\Models\NotificacionPlataforma;
use App\Models\SolicitudRecursos;
use App\Models\Suspension;
use App\Models\User;

beforeEach(function () {
    $this->tecnico = User::factory()->tecnico('CERRAJERIA')->create();
    $this->coordinador = $this->usuarioConRol(Rol::Coordinador);
    $this->autorizador = $this->usuarioConRol(Rol::Autorizador);
});

function solicitudRecursos(bool $requiereAutorizacion, float $costo = 350000, bool $emergencia = false): array
{
    return [
        'decision_requerida' => 'Compra de cerradura de alta seguridad fuera de caja menor',
        'requiere_autorizacion' => $requiereAutorizacion,
        'es_emergencia' => $emergencia,
        'items' => [['recurso_nombre' => 'Cerradura multipunto', 'cantidad' => 1, 'unidad' => 'und', 'costo_estimado' => $costo]],
    ];
}

it('registra recursos sujetos a autorización (E07 -> E08) y notifica al autorizador', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(true))
        ->assertCreated()
        ->assertJsonPath('data.estado_solicitud', 'PENDIENTE_AUTORIZACION')
        ->assertJsonPath('data.incidencia.estado.codigo', 'E08');

    expect(NotificacionPlataforma::query()->where('usuario_destino_id', $this->autorizador->id)->where('tipo', 'AUTORIZACION_PENDIENTE')->exists())->toBeTrue();

    $this->comoRol($this->autorizador, Rol::Autorizador)
        ->getJson('/api/v1/autorizaciones/pendientes')
        ->assertJsonPath('data.0.costo_total_estimado', 350000);
});

it('registra recursos sin autorización pero sin stock (E07 -> E09)', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(false))
        ->assertJsonPath('data.incidencia.estado.codigo', 'E09');
});

it('valida que la solicitud identifique el recurso y la decisión requerida (RN-027)', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", ['requiere_autorizacion' => true])
        ->assertJsonValidationErrors([
            'decision_requerida' => 'Debe indicar la decisión administrativa requerida (RN-027).',
            'items' => 'Debe identificar al menos un recurso requerido (RN-027).',
        ]);
});

it('bloquea iniciar trabajos mientras haya una autorización pendiente (RN-029)', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);
    $this->comoRol($this->coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(true));

    $this->comoRol($this->tecnico, Rol::Tecnico)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/iniciar-atencion")
        ->assertStatus(409)
        ->assertJsonPath('detail', 'La intervención requiere autorización previa aprobada antes de comenzar trabajos (RN-029).');
});

it('permite avanzar bajo emergencia autorizada con regularización posterior', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico, atributos: ['protocolo_emergencia_activo' => true, 'es_riesgo_inmediato' => true]);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(true, emergencia: true))
        ->assertCreated()
        ->assertJsonPath('data.es_emergencia', true)
        ->assertJsonPath('data.incidencia.estado.codigo', 'E07');

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/programar", ['fecha_programada' => now()->toDateString(), 'hora_inicio' => '08:00', 'hora_fin' => '09:00'])
        ->assertJsonPath('data.estado.codigo', 'E10');
});

it('rechaza la modalidad de emergencia sin protocolo de riesgo activo', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(true, emergencia: true))
        ->assertUnprocessable()
        ->assertJsonPath('regla', 'RN-029');
});

it('aprueba con recursos disponibles (E08 -> E10) o pendientes de compra (E08 -> E09)', function (bool $disponibles, string $estado) {
    $incidencia = incidenciaConDiagnostico($this->tecnico);
    $solicitud = $this->comoRol($this->coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(true))->json('data.id');

    $this->comoRol($this->autorizador, Rol::Autorizador)
        ->postJson("/api/v1/solicitudes-recursos/{$solicitud}/dictamen", ['resultado' => 'APROBADA', 'recursos_disponibles' => $disponibles, 'justificacion' => 'Presupuesto disponible en el rubro de mantenimiento'])
        ->assertOk()
        ->assertJsonPath('data.incidencia.estado.codigo', $estado);

    $registro = SolicitudRecursos::query()->findOrFail($solicitud);
    expect($registro->autorizador_id)->toBe($this->autorizador->id)->and($registro->fecha_dictamen)->not->toBeNull();
})->with([
    'recursos disponibles' => [true, 'E10'],
    'compra en trámite' => [false, 'E09'],
]);

it('rechaza con suspensión documentada y exige justificación (RN-028, RN-031)', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);
    $solicitud = $this->comoRol($this->coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(true))->json('data.id');

    $this->comoRol($this->autorizador, Rol::Autorizador)
        ->postJson("/api/v1/solicitudes-recursos/{$solicitud}/dictamen", ['resultado' => 'RECHAZADA', 'accion_rechazo' => 'SUSPENDER'])
        ->assertJsonValidationErrors(['justificacion' => 'Debe documentar el motivo de la decisión (RN-028).']);

    $this->comoRol($this->autorizador, Rol::Autorizador)
        ->postJson("/api/v1/solicitudes-recursos/{$solicitud}/dictamen", [
            'resultado' => 'RECHAZADA',
            'accion_rechazo' => 'SUSPENDER',
            'justificacion' => 'Inviable presupuestalmente en el periodo actual',
            'condiciones_reanudacion' => 'Asignación de presupuesto en la próxima vigencia',
        ])
        ->assertOk()
        ->assertJsonPath('data.estado_solicitud', 'RECHAZADA')
        ->assertJsonPath('data.incidencia.estado.codigo', 'E18');

    expect(Suspension::query()->where('incidencia_id', $incidencia->id)->value('causal'))->toBe('RECHAZO_AUTORIZACION');
});

it('deniega el dictamen a roles sin permiso y al coordinador por encima de su tope delegado', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);
    $solicitud = $this->comoRol($this->coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(true, 5000000))->json('data.id');

    $this->comoRol($this->usuarioConRol(Rol::Gestor), Rol::Gestor)
        ->postJson("/api/v1/solicitudes-recursos/{$solicitud}/dictamen", ['resultado' => 'APROBADA'])
        ->assertForbidden();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/solicitudes-recursos/{$solicitud}/dictamen", ['resultado' => 'APROBADA', 'recursos_disponibles' => true])
        ->assertForbidden();

    expect($incidencia->refresh()->estado_codigo)->toBe(EstadoIncidencia::PendienteAutorizacion);
});

it('mantiene E09 con notas de seguimiento y programa al confirmar todos los recursos (E09 -> E10)', function () {
    $incidencia = incidenciaConDiagnostico($this->tecnico);
    $solicitud = $this->comoRol($this->coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$incidencia->id}/solicitudes-recursos", solicitudRecursos(false))->json('data.id');

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/solicitudes-recursos/{$solicitud}/seguimientos", ['comentario' => 'El proveedor informa un retraso de 3 días'])
        ->assertCreated();
    expect($incidencia->refresh()->estado_codigo)->toBe(EstadoIncidencia::PendienteRecursos);

    $plan = ['fecha_programada' => now()->addDays(4)->toDateString(), 'hora_inicio' => '08:00', 'hora_fin' => '10:00'];

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->patchJson("/api/v1/incidencias/{$incidencia->id}/confirmar-recursos", $plan)
        ->assertJsonValidationErrors('detalles_disponibles');

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->patchJson("/api/v1/incidencias/{$incidencia->id}/confirmar-recursos", $plan + ['todos_disponibles' => true])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E10');

    expect(SolicitudRecursos::query()->find($solicitud)->estado_solicitud)->toBe(EstadoSolicitudRecursos::Disponible);
});
