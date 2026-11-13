<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\EspacioFisico;
use App\Models\Incidencia;

beforeEach(function () {
    $this->coordinador = $this->usuarioConRol(Rol::Coordinador);
});

it('calcula totales por estado coherentes, MTTR y fallas recurrentes', function () {
    $laboratorio = EspacioFisico::factory()->create(['nombre' => 'Laboratorio de Química 201']);
    Incidencia::factory()->count(4)->categoria('HIDROSANITARIA')->create(['espacio_fisico_id' => $laboratorio->id]);
    Incidencia::factory()->enEstado(EstadoIncidencia::Cerrada)->create(['created_at' => now()->subHours(10), 'fecha_cierre' => now()]);
    Incidencia::factory()->enEstado(EstadoIncidencia::Cerrada)->create(['created_at' => now()->subHours(20), 'fecha_cierre' => now()]);

    $respuesta = $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson('/api/v1/analitica/metricas-basicas?periodo=rango&desde='.now()->subDays(3)->toDateString().'&hasta='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.total_incidencias', 6)
        ->assertJsonPath('data.mttr.horas_promedio', 15)
        ->assertJsonPath('data.fallas_recurrentes.data.0.espacio', 'Laboratorio de Química 201')
        ->assertJsonPath('data.fallas_recurrentes.data.0.reportes', 4);

    expect(collect($respuesta->json('data.por_estado'))->sum('total'))->toBe(6);
});

it('responde sin errores ni divisiones por cero en periodos sin datos', function () {
    $this->comoRol($this->coordinador, Rol::Coordinador)
        ->getJson('/api/v1/analitica/metricas-basicas?periodo=rango&desde=2020-01-01&hasta=2020-01-31')
        ->assertOk()
        ->assertJsonPath('data.sin_datos', true)
        ->assertJsonPath('data.total_incidencias', 0)
        ->assertJsonPath('data.mttr.horas_promedio', null)
        ->assertJsonPath('data.cumplimiento_sla.porcentaje', null);
});
