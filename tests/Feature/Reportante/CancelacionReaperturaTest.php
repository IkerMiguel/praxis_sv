<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\ActividadMantenimiento;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\NotificacionPlataforma;

beforeEach(function () {
    $this->reportante = $this->usuarioConRol(Rol::Reportante);
});

it('cancela la solicitud antes de la asignación técnica con motivo obligatorio', function (EstadoIncidencia $estado) {
    $incidencia = Incidencia::factory()->for($this->reportante, 'creador')->enEstado($estado)->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cancelar-solicitante", ['motivo' => 'corto'])
        ->assertJsonValidationErrors(['motivo' => 'El motivo debe tener al menos 15 caracteres.']);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cancelar-solicitante", ['motivo' => 'El daño fue reparado por el docente.'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'E17');

    $incidencia->refresh();
    expect($incidencia->motivo_cancelacion)->toBe('El daño fue reparado por el docente.')
        ->and($incidencia->usuario_cancelacion_id)->toBe($this->reportante->id)
        ->and(EventoAuditoria::query()->where('incidencia_id', $incidencia->id)->where('estado_nuevo', 'E17')->exists())->toBeTrue();
})->with([EstadoIncidencia::Registrada, EstadoIncidencia::PendienteInformacion, EstadoIncidencia::EnRevision, EstadoIncidencia::EnClasificacion]);

it('impide cancelar cuando ya hay personal técnico asignado', function () {
    $incidencia = Incidencia::factory()->for($this->reportante, 'creador')->enEstado(EstadoIncidencia::PendienteInspeccion)->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/mis-solicitudes/{$incidencia->id}")
        ->assertJsonPath('data.acciones.puede_cancelar', false)
        ->assertJsonPath('data.acciones.mensaje_cancelacion', 'La solicitud ya cuenta con personal técnico asignado. Para novedades comuníquese con Coordinación de Mantenimiento');

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cancelar-solicitante", ['motivo' => 'Ya no necesito la reparación'])
        ->assertStatus(409)
        ->assertJsonPath('detail', 'La solicitud ya cuenta con personal técnico asignado. Para novedades comuníquese con Coordinación de Mantenimiento');
});

it('reabre dentro de la ventana de garantía hacia En corrección con prioridad Alta y alerta al coordinador', function () {
    $coordinador = $this->usuarioConRol(Rol::Coordinador);
    $tecnico = $this->usuarioConRol(Rol::Tecnico);
    $incidencia = Incidencia::factory()->for($this->reportante, 'creador')->asignadaA($tecnico)->cerradaHace(2)->prioridad('BAJA')->create();
    ActividadMantenimiento::create(['incidencia_id' => $incidencia->id, 'tecnico_ejecutor_id' => $tecnico->id, 'fecha_inicio' => now()->subDays(3), 'fecha_fin' => now()->subDays(3)]);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reabrir", ['motivo' => 'El lavamanos volvió a gotear por la base tras usarlo esta mañana'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'E13')
        ->assertJsonPath('data.numero_reaperturas', 1);

    $incidencia->refresh()->load('prioridad');
    expect($incidencia->prioridad->codigo)->toBe('ALTA')
        ->and($incidencia->fecha_cierre)->toBeNull()
        ->and(NotificacionPlataforma::query()->where('usuario_destino_id', $coordinador->id)->where('tipo', 'REAPERTURA')->exists())->toBeTrue();
});

it('reabre hacia Pendiente de asignación cuando no hubo intervención técnica', function () {
    $incidencia = Incidencia::factory()->for($this->reportante, 'creador')->cerradaHace(1)->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reabrir", ['motivo' => 'El problema persiste y nadie vino a revisarlo'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'E05');
});

it('rechaza la reapertura fuera de la ventana de garantía ofreciendo crear un nuevo reporte', function () {
    $incidencia = Incidencia::factory()->for($this->reportante, 'creador')->cerradaHace(15)->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reabrir", ['motivo' => 'El problema volvió a presentarse en el aula'])
        ->assertUnprocessable()
        ->assertJsonPath('alternativa', 'CREAR_NUEVO_REPORTE')
        ->assertJsonPath('detail', 'La ventana de garantía para reabrir la solicitud ya expiró.');
});

it('bloquea la reapertura al alcanzar el máximo de reaperturas', function () {
    $incidencia = Incidencia::factory()->for($this->reportante, 'creador')->cerradaHace(1)->create(['numero_reaperturas' => 2]);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/mis-solicitudes/{$incidencia->id}")
        ->assertJsonPath('data.acciones.puede_reabrir', false)
        ->assertJsonPath('data.acciones.puede_crear_reporte_basado', true);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reabrir", ['motivo' => 'El problema volvió a presentarse en el aula'])
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'La solicitud alcanzó el máximo de 2 reaperturas permitidas.');
});

it('exige un motivo de reapertura de al menos 20 caracteres', function () {
    $incidencia = Incidencia::factory()->for($this->reportante, 'creador')->cerradaHace(1)->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/reabrir", ['motivo' => 'Sigue igual'])
        ->assertJsonValidationErrors(['motivo' => 'El motivo debe tener al menos 20 caracteres.']);
});
