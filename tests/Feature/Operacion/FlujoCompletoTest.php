<?php

use App\Enums\EstadoIncidencia;
use App\Enums\Rol;
use App\Models\CategoriaIncidencia;
use App\Models\EspacioFisico;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\User;

it('recorre el ciclo completo E01 -> E03 -> E04 -> E05 -> E06 -> E07 -> E10 -> E11 -> E12 -> E14 -> E15 por la API', function () {
    $reportante = $this->usuarioConRol(Rol::Reportante);
    $gestor = $this->usuarioConRol(Rol::Gestor);
    $coordinador = $this->usuarioConRol(Rol::Coordinador);
    $tecnico = User::factory()->tecnico('CERRAJERIA')->create();
    $verificador = $this->usuarioConRol(Rol::Verificador);
    $espacio = EspacioFisico::factory()->create()->load('piso.edificio');
    $categoria = CategoriaIncidencia::query()->where('codigo', 'CERRAJERIA')->firstOrFail();

    $id = $this->comoRol($reportante, Rol::Reportante)->postJson('/api/v1/incidencias/radicar', [
        'sede_id' => $espacio->piso->edificio->sede_id,
        'edificio_id' => $espacio->piso->edificio_id,
        'piso_id' => $espacio->piso_id,
        'espacio_fisico_id' => $espacio->id,
        'categoria_id' => $categoria->id,
        'descripcion' => 'La cerradura de la puerta del laboratorio está trabada.',
    ])->assertCreated()->json('data.id');

    $this->comoRol($gestor, Rol::Gestor)->postJson("/api/v1/incidencias/{$id}/iniciar-revision")->assertJsonPath('data.estado.codigo', 'E03');
    $this->comoRol($gestor, Rol::Gestor)->postJson("/api/v1/incidencias/{$id}/clasificar", ['procedente' => true, 'impacto' => 'MEDIO', 'urgencia' => 'ALTA'])
        ->assertJsonPath('data.estado.codigo', 'E05')
        ->assertJsonPath('data.prioridad.codigo', 'ALTA');

    $this->comoRol($coordinador, Rol::Coordinador)->getJson("/api/v1/tecnicos/disponibles?categoria_id={$categoria->id}")
        ->assertJsonPath('data.0.id', $tecnico->id)
        ->assertJsonPath('data.0.carga_actual', 0);
    $this->comoRol($coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$id}/asignar", ['tecnico_id' => $tecnico->id, 'instrucciones' => 'Revisar la chapa principal.'])
        ->assertJsonPath('data.estado.codigo', 'E06');

    $this->comoRol($tecnico, Rol::Tecnico)->postJson("/api/v1/incidencias/{$id}/iniciar-inspeccion")->assertJsonPath('data.estado.codigo', 'E07');
    $this->comoRol($tecnico, Rol::Tecnico)->postJson("/api/v1/incidencias/{$id}/diagnostico", [
        'hallazgos' => 'El cilindro de la cerradura está desgastado.',
        'descripcion_problema' => 'Desgaste mecánico del cilindro por uso.',
        'intervencion_propuesta' => 'Cambiar el cilindro de la cerradura.',
    ])->assertOk();

    $this->comoRol($coordinador, Rol::Coordinador)->postJson("/api/v1/incidencias/{$id}/programar", [
        'fecha_programada' => now()->addDay()->toDateString(),
        'hora_inicio' => '08:00',
        'hora_fin' => '11:00',
    ])->assertJsonPath('data.estado.codigo', 'E10');

    $this->comoRol($tecnico, Rol::Tecnico)->postJson("/api/v1/incidencias/{$id}/iniciar-atencion")->assertJsonPath('data.estado.codigo', 'E11');
    $evidencias = $this->subirEvidencias($tecnico, Rol::Tecnico, 1, ['incidencia_id' => $id, 'etapa' => 'EJECUCION']);
    expect($evidencias)->toHaveCount(1);

    $this->comoRol($tecnico, Rol::Tecnico)->postJson("/api/v1/incidencias/{$id}/registrar-intervencion", [
        'actividades_realizadas' => 'Se reemplazó el cilindro y se lubricó el mecanismo.',
        'tiempo_invertido_minutos' => 45,
        'materiales' => [['nombre' => 'Cilindro de seguridad', 'cantidad' => 1, 'unidad' => 'und']],
    ])->assertJsonPath('data.estado.codigo', 'E12');

    $this->comoRol($verificador, Rol::Verificador)->postJson("/api/v1/incidencias/{$id}/verificar", [
        'criterios' => [['criterio' => 'La puerta cierra correctamente', 'cumple' => true]],
    ])->assertJsonPath('data.estado.codigo', 'E14');

    $this->comoRol($reportante, Rol::Reportante)->postJson("/api/v1/incidencias/{$id}/confirmar", ['calificacion' => 5, 'comentario' => 'Reparación concluida satisfactoriamente'])
        ->assertJsonPath('data.estado', 'E15');

    $incidencia = Incidencia::query()->findOrFail($id);
    expect($incidencia->estado_codigo)->toBe(EstadoIncidencia::Cerrada)
        ->and($incidencia->calificacion_servicio)->toBe(5)
        ->and($incidencia->fecha_cierre)->not->toBeNull()
        ->and($incidencia->sla_respuesta_cumplido)->toBeTrue()
        ->and($incidencia->sla_resolucion_cumplido)->not->toBeNull();

    $estados = EventoAuditoria::query()->where('incidencia_id', $id)->whereNotNull('estado_nuevo')->orderBy('fecha_hora')->pluck('estado_nuevo')->all();
    expect($estados)->toBe(['E01', 'E03', 'E04', 'E05', 'E06', 'E07', 'E10', 'E11', 'E12', 'E14', 'E15']);

    $this->comoRol($reportante, Rol::Reportante)->getJson("/api/v1/incidencias/{$id}/historial")
        ->assertJsonPath('alcance', 'PUBLICO')
        ->assertJsonPath('data.0.estado_nuevo.codigo', 'E01');

    $this->comoRol($coordinador, Rol::Coordinador)->getJson("/api/v1/incidencias/{$id}/historial?categoria=FORENSE")
        ->assertJsonPath('alcance', 'COMPLETO')
        ->assertJsonPath('data.0.tipo_evento', 'CAMBIO_PRIORIDAD');
});
