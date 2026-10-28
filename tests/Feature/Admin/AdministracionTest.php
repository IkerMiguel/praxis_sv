<?php

use App\Enums\Impacto;
use App\Enums\Rol;
use App\Enums\Urgencia;
use App\Models\CategoriaIncidencia;
use App\Models\EspacioFisico;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\Piso;
use App\Models\User;
use App\Services\CatalogoService;
use App\Services\ParametrosService;
use App\Support\Parametro;

beforeEach(function () {
    $this->admin = $this->usuarioConRol(Rol::Administrador);
});

it('crea un usuario con múltiples roles y registra la acción en auditoría', function () {
    $respuesta = $this->comoRol($this->admin, Rol::Administrador)
        ->postJson('/api/v1/admin/usuarios', [
            'nombre' => 'Funcionario Nuevo',
            'email' => 'nuevo@ecci.edu.co',
            'password' => 'Clave*Segura1',
            'roles' => ['R02', 'R03'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.roles.*.codigo', ['R02', 'R03']);

    expect(EventoAuditoria::query()->where('tipo_evento', 'USUARIO_CREADO')->where('entidad_id', (string) $respuesta->json('data.id'))->where('usuario_id', $this->admin->id)->exists())->toBeTrue();
});

it('inactiva usuarios revocando sesiones y los excluye de técnicos disponibles', function () {
    $tecnico = User::factory()->tecnico('CERRAJERIA')->create();
    $tecnico->createToken('praxis-api');

    $this->comoRol($this->admin, Rol::Administrador)
        ->patchJson("/api/v1/admin/usuarios/{$tecnico->id}/estado", ['activo' => false, 'motivo' => 'Retiro de la institución'])
        ->assertOk()
        ->assertJsonPath('data.activo', false);

    expect($tecnico->tokens()->count())->toBe(0);

    $this->comoRol($this->usuarioConRol(Rol::Coordinador), Rol::Coordinador)
        ->getJson('/api/v1/tecnicos/disponibles')
        ->assertJsonCount(0, 'data');
});

it('impide quitar o inactivar al único administrador activo', function () {
    $this->comoRol($this->admin, Rol::Administrador)
        ->putJson("/api/v1/admin/usuarios/{$this->admin->id}/roles", ['roles' => ['R01']])
        ->assertStatus(409)
        ->assertJsonPath('detail', 'Debe existir al menos un administrador activo en la plataforma.');

    $this->comoRol($this->admin, Rol::Administrador)
        ->patchJson("/api/v1/admin/usuarios/{$this->admin->id}/estado", ['activo' => false])
        ->assertStatus(409);
});

it('no permite eliminar físicamente usuarios', function () {
    $this->comoRol($this->admin, Rol::Administrador)
        ->deleteJson("/api/v1/admin/usuarios/{$this->admin->id}")
        ->assertStatus(405);
});

it('permite al coordinador solo lectura en administración', function () {
    $coordinador = $this->usuarioConRol(Rol::Coordinador);

    $this->comoRol($coordinador, Rol::Coordinador)->getJson('/api/v1/admin/usuarios')->assertOk();
    $this->comoRol($coordinador, Rol::Coordinador)
        ->postJson('/api/v1/admin/usuarios', ['nombre' => 'X', 'email' => 'x@ecci.edu.co', 'roles' => ['R01']])
        ->assertForbidden();
});

it('crea la jerarquía de ubicaciones, evita duplicados y la expone en el árbol activo', function () {
    $sede = $this->comoRol($this->admin, Rol::Administrador)->postJson('/api/v1/admin/ubicaciones/sedes', ['codigo' => 'NORTE', 'nombre' => 'Sede Norte'])->assertCreated()->json('data.id');
    $edificio = $this->comoRol($this->admin, Rol::Administrador)->postJson('/api/v1/admin/ubicaciones/edificios', ['sede_id' => $sede, 'codigo' => 'C', 'nombre' => 'Bloque C'])->json('data.id');
    $piso = $this->comoRol($this->admin, Rol::Administrador)->postJson('/api/v1/admin/ubicaciones/pisos', ['edificio_id' => $edificio, 'codigo' => 'P2', 'nombre' => 'Piso 2', 'nivel' => 2])->json('data.id');

    $this->comoRol($this->admin, Rol::Administrador)->getJson('/api/v1/catalogo/ubicaciones/arbol-activo')->assertJsonCount(1, 'data');

    $this->comoRol($this->admin, Rol::Administrador)->postJson('/api/v1/admin/ubicaciones/espacios', ['piso_id' => $piso, 'codigo' => 'LAB-204', 'nombre' => 'Laboratorio de Física 204'])->assertCreated();
    $this->comoRol($this->admin, Rol::Administrador)
        ->postJson('/api/v1/admin/ubicaciones/espacios', ['piso_id' => $piso, 'codigo' => 'LAB-204', 'nombre' => 'Otro nombre'])
        ->assertJsonValidationErrors(['codigo' => 'Esa ubicación ya se encuentra parametrizada.']);

    $this->comoRol($this->admin, Rol::Administrador)
        ->getJson('/api/v1/catalogo/ubicaciones/arbol-activo')
        ->assertJsonPath('data.0.edificios.0.pisos.0.espacios.0.nombre', 'Laboratorio de Física 204');
});

it('inactiva espacios con historial en lugar de borrarlos, conservando las incidencias antiguas', function () {
    $espacio = EspacioFisico::factory()->create();
    $incidencia = Incidencia::factory()->create(['espacio_fisico_id' => $espacio->id]);

    $this->comoRol($this->admin, Rol::Administrador)->deleteJson("/api/v1/admin/ubicaciones/espacios/{$espacio->id}")->assertStatus(409);
    $this->comoRol($this->admin, Rol::Administrador)->patchJson("/api/v1/admin/ubicaciones/espacios/{$espacio->id}/estado", ['activo' => false])->assertOk();

    $this->comoRol($this->admin, Rol::Administrador)
        ->getJson("/api/v1/incidencias/{$incidencia->id}")
        ->assertJsonPath('data.ubicacion.espacio.id', $espacio->id);
    expect(app(CatalogoService::class)->arbolUbicacionesActivas()[0]['edificios'][0]['pisos'][0]['espacios'])->toBe([])
        ->and(Piso::query()->count())->toBe(1);
});

it('actualiza la obligatoriedad de evidencia de una categoría con invalidación de caché', function () {
    $categoria = CategoriaIncidencia::query()->where('codigo', 'HIDROSANITARIA')->firstOrFail();
    app(CatalogoService::class)->categoriasActivas();

    $this->comoRol($this->admin, Rol::Administrador)
        ->putJson("/api/v1/admin/categorias/{$categoria->id}", ['exige_evidencia_fotografica' => true])
        ->assertOk();

    expect(collect(app(CatalogoService::class)->categoriasActivas())->firstWhere('codigo', 'HIDROSANITARIA')['exige_evidencia_fotografica'])->toBeTrue()
        ->and(EventoAuditoria::query()->where('tipo_evento', 'CATEGORIA_ACTUALIZADA')->exists())->toBeTrue();
});

it('recalibra la matriz ITIL en caliente y audita valor anterior y nuevo', function () {
    $this->comoRol($this->admin, Rol::Administrador)
        ->putJson('/api/v1/admin/parametros', [
            'matriz' => [['impacto' => 'MEDIO', 'urgencia' => 'MEDIA', 'prioridad' => 'MEDIA']],
            'parametros' => [Parametro::VENTANA_REAPERTURA_DIAS_HABILES => 3],
        ])
        ->assertOk();

    expect(app(CatalogoService::class)->prioridadPara(Impacto::Medio, Urgencia::Media)->codigo)->toBe('MEDIA')
        ->and(app(ParametrosService::class)->entero(Parametro::VENTANA_REAPERTURA_DIAS_HABILES))->toBe(3);

    $evento = EventoAuditoria::query()->where('tipo_evento', 'PARAMETRO_ACTUALIZADO')->firstOrFail();
    expect($evento->datos)->toMatchArray(['valor_anterior' => '5', 'valor_nuevo' => '3'])
        ->and($evento->usuario_id)->toBe($this->admin->id);

    $this->comoRol($this->admin, Rol::Administrador)->getJson('/api/v1/admin/parametros/historial')->assertJsonCount(2, 'data');
});

it('rechaza valores negativos o no numéricos en los parámetros', function (mixed $valor) {
    $this->comoRol($this->admin, Rol::Administrador)
        ->putJson('/api/v1/admin/parametros', ['parametros' => [Parametro::LIMITE_WIP_TECNICO => $valor]])
        ->assertJsonValidationErrors(['parametros.'.Parametro::LIMITE_WIP_TECNICO => 'El parámetro debe ser un número entero positivo mayor a cero']);
})->with([-3, 'abc', 0]);

it('ajusta el SLA de una prioridad para las nuevas incidencias', function () {
    $this->comoRol($this->admin, Rol::Administrador)
        ->putJson('/api/v1/admin/parametros', ['sla' => [['codigo' => 'CRITICA', 'horas_primera_respuesta' => 1, 'horas_resolucion' => 4]]])
        ->assertOk()
        ->assertJsonFragment(['codigo' => 'CRITICA', 'horas_resolucion' => 4]);

    expect(app(CatalogoService::class)->prioridadPorCodigo('CRITICA')->horas_resolucion)->toBe(4);
});
