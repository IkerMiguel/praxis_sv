<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\Incidencia;
use App\Models\User;
use App\Support\ResultadoCierre;
use Database\Seeders\UbicacionesSeeder;

beforeEach(function () {
    $this->reportante = $this->usuarioConRol(Rol::Reportante);
});

it('lista solo las solicitudes propias ordenadas de la más reciente a la más antigua', function () {
    $antigua = Incidencia::factory()->for($this->reportante, 'creador')->create(['created_at' => now()->subDays(3)]);
    $reciente = Incidencia::factory()->for($this->reportante, 'creador')->create(['created_at' => now()->subHour()]);
    Incidencia::factory()->count(3)->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/mis-solicitudes')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $reciente->id)
        ->assertJsonPath('data.1.id', $antigua->id)
        ->assertJsonPath('data.0.estado.nombre', 'Registrada');
});

it('filtra por estado y busca por código o resumen con estado vacío controlado', function () {
    $pendiente = Incidencia::factory()->for($this->reportante, 'creador')->enEstado(EstadoIncidencia::PendienteInformacion)->create(['resumen' => 'Fuga en lavamanos']);
    Incidencia::factory()->for($this->reportante, 'creador')->create(['resumen' => 'Silla rota']);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/mis-solicitudes?estado=E02')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $pendiente->id);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/mis-solicitudes?q=lavaMANOS')
        ->assertJsonPath('meta.total', 1);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/mis-solicitudes?q='.$pendiente->codigo_ticket)
        ->assertJsonPath('data.0.codigo_ticket', $pendiente->codigo_ticket);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/mis-solicitudes?q=inexistente')
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.sin_resultados', true);
});

it('muestra la ficha con historial público y la incidencia principal de un duplicado (RN-015)', function () {
    $principal = Incidencia::factory()->enEstado(EstadoIncidencia::EnAtencion)->create();
    $duplicada = Incidencia::factory()->for($this->reportante, 'creador')->cerradaHace(0)->create([
        'incidencia_principal_id' => $principal->id,
        'resultado_final_cierre' => ResultadoCierre::VINCULADA_POR_DUPLICIDAD,
    ]);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/mis-solicitudes/{$duplicada->id}")
        ->assertOk()
        ->assertJsonPath('data.codigo_ticket', $duplicada->codigo_ticket)
        ->assertJsonPath('data.incidencia_principal.codigo_ticket', $principal->codigo_ticket)
        ->assertJsonPath('data.incidencia_principal.estado.codigo', 'E11')
        ->assertJsonPath('data.acciones.puede_reabrir', false);
});

it('responde 404 al consultar solicitudes de otros usuarios (anti-IDOR)', function () {
    $ajena = Incidencia::factory()->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/mis-solicitudes/{$ajena->id}")
        ->assertNotFound();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/mis-solicitudes/no-es-uuid')
        ->assertNotFound();
});

it('cuadra los contadores del panel con el total de mis solicitudes y aísla las de otros usuarios', function () {
    Incidencia::factory()->for($this->reportante, 'creador')->count(2)->enEstado(EstadoIncidencia::PendienteInformacion)->create();
    Incidencia::factory()->for($this->reportante, 'creador')->enEstado(EstadoIncidencia::PendienteConfirmacion)->create();
    Incidencia::factory()->for($this->reportante, 'creador')->create();
    Incidencia::factory()->count(5)->create();

    $panel = $this->comoRol($this->reportante, Rol::Reportante)->getJson('/api/v1/panel/resumen')->assertOk();
    $listado = $this->comoRol($this->reportante, Rol::Reportante)->getJson('/api/v1/mis-solicitudes')->json('meta.total');

    expect($panel->json('data.total'))->toBe(4)
        ->and(collect($panel->json('data.por_estado'))->sum('total'))->toBe($listado)
        ->and($panel->json('data.requieren_accion.pendiente_informacion'))->toBe(2)
        ->and($panel->json('data.requieren_accion.pendiente_confirmacion'))->toBe(1)
        ->and($panel->json('data.recientes'))->toHaveCount(4);
});

it('muestra contadores en cero para un usuario sin solicitudes', function () {
    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/panel/resumen')
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.recientes', []);
});

it('expone el glosario de estados D07 indicando cuándo debe intervenir el reportante', function () {
    $glosario = $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/catalogo/glosario-estados')
        ->assertOk()
        ->assertJsonCount(18, 'data');

    expect(collect($glosario->json('data'))->where('requiere_accion_reportante', true)->pluck('codigo')->all())->toBe(['E02', 'E14']);
});

it('entrega el árbol de ubicaciones activas y las categorías con subcategorías', function () {
    $this->seed(UbicacionesSeeder::class);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/catalogo/ubicaciones/arbol-activo')
        ->assertOk()
        ->assertJsonPath('data.0.nombre', 'Campus Principal')
        ->assertJsonPath('data.0.edificios.0.pisos.0.espacios.0.nombre', 'Aula 101');

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/catalogo/categorias')
        ->assertOk()
        ->assertJsonFragment(['codigo' => 'ESTRUCTURAL_TECHOS', 'exige_evidencia_fotografica' => true]);
});

it('permite al seguidor ver la ficha en modo seguimiento pero no actuar sobre ella', function () {
    $otro = User::factory()->conRoles(Rol::Reportante)->create();
    $incidencia = Incidencia::factory()->for($otro, 'creador')->create();
    $incidencia->seguidores()->attach($this->reportante->id);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/mis-solicitudes/{$incidencia->id}")
        ->assertOk()
        ->assertJsonPath('data.es_seguimiento', true)
        ->assertJsonPath('data.acciones.puede_cancelar', false);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$incidencia->id}/cancelar-solicitante", ['motivo' => 'Ya no es necesario atenderla'])
        ->assertNotFound();
});
