<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\Edificio;
use App\Models\EspacioFisico;
use App\Models\Incidencia;
use App\Models\Piso;
use App\Models\Sede;
use App\Models\User;
use App\Services\CalculadoraSla;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->coordinador = $this->usuarioConRol(Rol::Coordinador);
});

function espacioEnSede(Sede $sede): EspacioFisico
{
    return EspacioFisico::factory()->for(Piso::factory()->for(Edificio::factory()->for($sede)))->create();
}

it('aplica filtros combinados de sede, estado y prioridad en la consola', function () {
    $central = Sede::factory()->create(['nombre' => 'Campus Central']);
    $esperada = Incidencia::factory()->enEstado(EstadoIncidencia::EnAtencion)->prioridad('ALTA')->create(['espacio_fisico_id' => espacioEnSede($central)->id]);
    Incidencia::factory()->enEstado(EstadoIncidencia::EnAtencion)->prioridad('BAJA')->create(['espacio_fisico_id' => espacioEnSede($central)->id]);
    Incidencia::factory()->enEstado(EstadoIncidencia::Registrada)->prioridad('ALTA')->create(['espacio_fisico_id' => espacioEnSede($central)->id]);
    Incidencia::factory()->enEstado(EstadoIncidencia::EnAtencion)->prioridad('ALTA')->create();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson("/api/v1/consola/incidencias?sede_id={$central->id}&estado=E11&prioridad=ALTA")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $esperada->id)
        ->assertJsonPath('data.0.ubicacion.sede.nombre', 'Campus Central');
});

it('informa sin resultados ante búsquedas sin coincidencias', function () {
    Incidencia::factory()->create();

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson('/api/v1/consola/incidencias?q=no-existe')
        ->assertJsonPath('meta.sin_resultados', true);
});

it('muestra la duración neta de atención en incidencias cerradas', function () {
    Incidencia::factory()->cerradaHace(0)->create(['created_at' => now()->subHours(32), 'fecha_cierre' => now()]);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson('/api/v1/consola/incidencias?estado=E15')
        ->assertJsonPath('data.0.tiempo_atencion.texto', '32 horas')
        ->assertJsonPath('data.0.tiempo_atencion.en_curso', false);
});

it('restringe la consola al reportante y limita al técnico a sus asignaciones', function () {
    $tecnico = User::factory()->tecnico('CERRAJERIA')->create();
    $propia = Incidencia::factory()->asignadaA($tecnico)->enEstado(EstadoIncidencia::Programada)->create();
    $ajena = Incidencia::factory()->create();

    $this->comoRol($this->usuarioConRol(Rol::Reportante), Rol::Reportante)
        ->getJson('/api/v1/consola/incidencias')
        ->assertForbidden();

    $this->comoRol($tecnico, Rol::Tecnico)
        ->getJson('/api/v1/consola/incidencias')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $propia->id);

    $this->comoRol($tecnico, Rol::Tecnico)->getJson("/api/v1/incidencias/{$ajena->id}")->assertNotFound();
    $this->comoRol($tecnico, Rol::Tecnico)->getJson("/api/v1/incidencias/{$propia->id}")->assertOk()->assertJsonPath('data.codigo_ticket', $propia->codigo_ticket);
});

it('calcula plazos en horas hábiles excluyendo fines de semana y feriados', function () {
    $sla = app(CalculadoraSla::class);
    $viernes = CarbonImmutable::parse('2026-11-13 16:00', 'America/Bogota');

    expect($sla->sumarHorasHabiles($viernes, 4)->setTimezone('America/Bogota')->format('Y-m-d H:i'))->toBe('2026-11-17 10:00')
        ->and($sla->minutosHabilesEntre($viernes, CarbonImmutable::parse('2026-11-17 10:00', 'America/Bogota')))->toBe(240);
});

it('clasifica el semáforo SLA en plazo, próxima a vencer y vencida', function (int $horasTranscurridas, string $estado) {
    $inicio = CarbonImmutable::parse('2026-11-02 08:00', 'America/Bogota');
    $incidencia = Incidencia::factory()->prioridad('MEDIA')->enEstado(EstadoIncidencia::EnAtencion)->create(['created_at' => $inicio]);
    $sla = app(CalculadoraSla::class);
    $incidencia->load('prioridad');
    $sla->fijarMetas($incidencia);
    $incidencia->save();

    expect($sla->evaluarResolucion($incidencia, $sla->sumarHorasHabiles($inicio, $horasTranscurridas))['estado'])->toBe($estado);
})->with([
    'en plazo' => [12, 'EN_PLAZO'],
    'próxima a vencer' => [40, 'PROXIMA_A_VENCER'],
    'vencida' => [50, 'VENCIDA'],
]);

it('congela el SLA al cerrar y lo reporta en la vista de plazos', function () {
    Incidencia::factory()->prioridad('ALTA')->enEstado(EstadoIncidencia::EnAtencion)->create([
        'created_at' => now()->subDays(10),
        'fecha_limite_resolucion' => now()->subDays(5),
    ]);
    Incidencia::factory()->prioridad('MEDIA')->enEstado(EstadoIncidencia::EnAtencion)->create([
        'fecha_limite_resolucion' => now()->addDays(10),
    ]);

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson('/api/v1/consola/plazos')
        ->assertOk()
        ->assertJsonPath('resumen.vencidas', 1)
        ->assertJsonPath('resumen.en_plazo', 1)
        ->assertJsonPath('data.0.sla.estado', 'VENCIDA');

    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson('/api/v1/consola/plazos?estado_sla=VENCIDA')
        ->assertJsonCount(1, 'data');
});
