<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\AsignacionIncidencia;
use App\Models\CategoriaIncidencia;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\NotificacionPlataforma;
use App\Models\User;
use App\Services\MaquinaEstados;
use App\Support\ContextoRol;

beforeEach(function () {
    $this->coordinador = $this->usuarioConRol(Rol::Coordinador);
    $this->cerrajero = User::factory()->tecnico('CERRAJERIA')->create();
});

function incidenciaPorAsignar(string $prioridad = 'MEDIA', array $atributos = []): Incidencia
{
    return Incidencia::factory()->enEstado(EstadoIncidencia::PendienteAsignacion)->categoria('CERRAJERIA')->prioridad($prioridad)->create($atributos);
}

function cargarTecnico(User $tecnico, int $cantidad): void
{
    Incidencia::factory()->count($cantidad)->enEstado(EstadoIncidencia::PendienteInspeccion)->asignadaA($tecnico)->create();
}

it('asigna un técnico de la especialidad (E05 -> E06), registra el responsable y lo notifica', function () {
    $incidencia = incidenciaPorAsignar();
    cargarTecnico($this->cerrajero, 2);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/asignar", ['tecnico_id' => $this->cerrajero->id, 'instrucciones' => 'Llevar juego de cilindros'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E06')
        ->assertJsonPath('data.excepcion_wip', false);

    expect($incidencia->refresh()->tecnico_asignado_id)->toBe($this->cerrajero->id)
        ->and(AsignacionIncidencia::query()->where('incidencia_id', $incidencia->id)->where('es_vigente', true)->value('asignado_por_id'))->toBe($this->coordinador->id)
        ->and(NotificacionPlataforma::query()->where('usuario_destino_id', $this->cerrajero->id)->where('tipo', 'ASIGNACION')->exists())->toBeTrue();
});

it('lista solo técnicos activos de la especialidad con su carga actual', function () {
    cargarTecnico($this->cerrajero, 3);
    User::factory()->tecnico('ELECTRICIDAD')->create();
    User::factory()->tecnico('CERRAJERIA')->inactivo()->create();
    $categoria = CategoriaIncidencia::query()->where('codigo', 'CERRAJERIA')->value('id');

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson("/api/v1/tecnicos/disponibles?categoria_id={$categoria}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.carga_actual', 3)
        ->assertJsonPath('data.0.limite_wip', 5)
        ->assertJsonPath('data.0.disponible', true);
});

it('rechaza asignar a usuarios sin rol R04 activo o de otra especialidad', function () {
    $incidencia = incidenciaPorAsignar();
    $electricista = User::factory()->tecnico('ELECTRICIDAD')->create();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/asignar", ['tecnico_id' => $this->coordinador->id])
        ->assertJsonValidationErrors(['tecnico_id' => 'El usuario seleccionado no es un técnico de mantenimiento (R04) activo.']);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/asignar", ['tecnico_id' => $electricista->id])
        ->assertJsonValidationErrors('tecnico_id');
});

it('bloquea la asignación cuando el técnico alcanzó el límite WIP', function () {
    $incidencia = incidenciaPorAsignar();
    cargarTecnico($this->cerrajero, 5);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/asignar", ['tecnico_id' => $this->cerrajero->id])
        ->assertUnprocessable()
        ->assertJsonPath('detail', 'El técnico ha alcanzado el límite de 5 órdenes activas. Seleccione otro especialista disponible o espere liberación de carga');

    expect($incidencia->refresh()->estado_codigo)->toBe(EstadoIncidencia::PendienteAsignacion);
});

it('permite la sobrecarga en prioridad crítica documentando la excepción', function () {
    $incidencia = incidenciaPorAsignar('CRITICA');
    cargarTecnico($this->cerrajero, 5);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/asignar", ['tecnico_id' => $this->cerrajero->id])
        ->assertOk()
        ->assertJsonPath('data.excepcion_wip', true);

    expect(EventoAuditoria::query()->where('incidencia_id', $incidencia->id)->where('tipo_evento', 'EXCEPCION_WIP')->exists())->toBeTrue();
});

it('reasigna con motivo obligatorio conservando técnico anterior, nuevo y motivo (RN-025)', function () {
    $nuevo = User::factory()->tecnico('CERRAJERIA')->create();
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::PendienteInspeccion)->categoria('CERRAJERIA')->asignadaA($this->cerrajero)->create();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/asignar", ['tecnico_id' => $nuevo->id])
        ->assertJsonValidationErrors(['motivo_reasignacion' => 'La reasignación exige registrar el motivo del relevo (RN-025).']);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/asignar", ['tecnico_id' => $nuevo->id, 'motivo_reasignacion' => 'Incapacidad médica del titular'])
        ->assertOk()
        ->assertJsonPath('data.estado.codigo', 'E06');

    $evento = EventoAuditoria::query()->where('tipo_evento', 'REASIGNACION')->firstOrFail();
    expect($evento->motivo)->toBe('Incapacidad médica del titular')
        ->and($evento->datos['tecnico_anterior_id'])->toBe($this->cerrajero->id)
        ->and($evento->datos['tecnico_id'])->toBe($nuevo->id)
        ->and(AsignacionIncidencia::query()->where('incidencia_id', $incidencia->id)->where('es_vigente', true)->count())->toBe(1);
});

it('impide iniciar inspección o atención sin responsable asignado (RN-021)', function () {
    $incidencia = Incidencia::factory()->enEstado(EstadoIncidencia::PendienteInspeccion)->create();
    app(ContextoRol::class)->establecer(Rol::Administrador);

    expect(fn () => app(MaquinaEstados::class)->transicionar($incidencia, EstadoIncidencia::EnDiagnostico, $this->coordinador))
        ->toThrow(ReglaNegocioException::class, 'RN-021');
});
