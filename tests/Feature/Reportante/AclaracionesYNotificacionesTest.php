<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\NotificacionPlataforma;
use App\Models\SolicitudAclaracion;
use App\Support\ResultadoCierre;

beforeEach(function () {
    $this->reportante = $this->usuarioConRol(Rol::Reportante);
    $this->gestor = $this->usuarioConRol(Rol::Gestor);
    $this->incidencia = Incidencia::factory()->for($this->reportante, 'creador')->enEstado(EstadoIncidencia::EnRevision)->create();
});

it('notifica al reportante la aclaración solicitada y la marca como leída', function () {
    $this->comoRol($this->gestor, Rol::Gestor)
        ->postJson("/api/v1/incidencias/{$this->incidencia->id}/solicitar-aclaracion", ['informacion_requerida' => 'Indique el número exacto del aula y adjunte una foto.'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E02');

    $bandeja = $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/notificaciones')
        ->assertOk()
        ->assertJsonPath('meta.no_leidas', 1)
        ->assertJsonPath('data.0.titulo', "Tu solicitud #{$this->incidencia->codigo_ticket} requiere información adicional")
        ->assertJsonPath('data.0.incidencia.id', $this->incidencia->id);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->patchJson('/api/v1/notificaciones/'.$bandeja->json('data.0.id').'/leer')
        ->assertOk()
        ->assertJsonPath('data.leida', true)
        ->assertJsonPath('meta.no_leidas', 0);

    expect(EventoAuditoria::query()->where('incidencia_id', $this->incidencia->id)->where('tipo_evento', 'COMUNICACION')->exists())->toBeTrue();
});

it('no permite leer notificaciones de otro usuario', function () {
    $this->comoRol($this->gestor, Rol::Gestor)
        ->postJson("/api/v1/incidencias/{$this->incidencia->id}/solicitar-aclaracion", ['informacion_requerida' => 'Indique el número exacto del aula.']);
    $id = NotificacionPlataforma::query()->where('usuario_destino_id', $this->reportante->id)->value('id');

    $this->comoRol($this->gestor, Rol::Gestor)->patchJson("/api/v1/notificaciones/{$id}/leer")->assertNotFound();
});

it('exige indicar qué información falta al solicitar aclaración (RN-007)', function () {
    $this->comoRol($this->gestor, Rol::Gestor)
        ->postJson("/api/v1/incidencias/{$this->incidencia->id}/solicitar-aclaracion", [])
        ->assertJsonValidationErrors(['informacion_requerida' => 'Debe indicar explícitamente qué datos debe aportar el reportante (RN-007).']);

    expect($this->incidencia->refresh()->estado_codigo)->toBe(EstadoIncidencia::EnRevision);
});

it('registra la aclaración del reportante y devuelve la incidencia a revisión (E02 -> E03)', function () {
    $this->comoRol($this->gestor, Rol::Gestor)
        ->postJson("/api/v1/incidencias/{$this->incidencia->id}/solicitar-aclaracion", ['informacion_requerida' => 'Indique el número exacto del aula.']);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/mis-solicitudes/{$this->incidencia->id}")
        ->assertJsonPath('data.aclaracion_pendiente.pregunta', 'Indique el número exacto del aula.')
        ->assertJsonPath('data.acciones.puede_responder_aclaracion', true);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/mis-solicitudes/{$this->incidencia->id}/aclaraciones", ['respuesta' => str_repeat('x', 501)])
        ->assertJsonValidationErrors(['respuesta' => 'La aclaración no puede superar los 500 caracteres.']);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/mis-solicitudes/{$this->incidencia->id}/aclaraciones", ['respuesta' => 'Es el aula 302 del bloque A.'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'E03');

    expect(SolicitudAclaracion::query()->first()->respuesta_reportante)->toBe('Es el aula 302 del bloque A.');
});

it('rechaza aclaraciones cuando la solicitud no está en Pendiente de información', function () {
    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/mis-solicitudes/{$this->incidencia->id}/aclaraciones", ['respuesta' => 'Información adicional'])
        ->assertStatus(409)
        ->assertJsonPath('regla', 'RN-008');
});

it('cancela por falta de respuesta las aclaraciones vencidas (RN-010)', function () {
    $this->comoRol($this->gestor, Rol::Gestor)
        ->postJson("/api/v1/incidencias/{$this->incidencia->id}/solicitar-aclaracion", ['informacion_requerida' => 'Indique el número exacto del aula.']);

    $this->artisan('praxis:vencer-aclaraciones')->assertSuccessful();
    expect($this->incidencia->refresh()->estado_codigo)->toBe(EstadoIncidencia::PendienteInformacion);

    $this->travel(10)->days();
    $this->artisan('praxis:vencer-aclaraciones')->expectsOutputToContain('1')->assertSuccessful();

    $incidencia = $this->incidencia->refresh();
    expect($incidencia->estado_codigo)->toBe(EstadoIncidencia::Cancelada)
        ->and($incidencia->resultado_final_cierre)->toBe(ResultadoCierre::CANCELADA_POR_FALTA_DE_RESPUESTA)
        ->and(EventoAuditoria::query()->where('tipo_evento', 'CADUCIDAD_ACLARACION')->value('rol_codigo'))->toBe('SISTEMA');
});

it('marca todas las notificaciones como leídas', function () {
    $this->comoRol($this->gestor, Rol::Gestor)
        ->postJson("/api/v1/incidencias/{$this->incidencia->id}/solicitar-aclaracion", ['informacion_requerida' => 'Indique el número exacto del aula.']);

    $this->comoRol($this->reportante, Rol::Reportante)->patchJson('/api/v1/notificaciones/leer-todas')->assertJsonPath('marcadas', 1);
    $this->comoRol($this->reportante, Rol::Reportante)->getJson('/api/v1/notificaciones?solo_no_leidas=1')->assertJsonPath('meta.no_leidas', 0)->assertJsonCount(0, 'data');
});
