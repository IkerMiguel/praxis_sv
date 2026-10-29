<?php

use App\Enums\Rol;
use App\Models\CategoriaIncidencia;
use App\Models\EspacioFisico;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\NotificacionPlataforma;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->reportante = $this->usuarioConRol(Rol::Reportante);
    $this->espacio = EspacioFisico::factory()->create();
    $this->espacio->load('piso.edificio');
});

function datosRadicacion(EspacioFisico $espacio, string $categoria = 'CERRAJERIA', array $extra = []): array
{
    $categoriaPrincipal = CategoriaIncidencia::query()->where('codigo', $categoria)->firstOrFail();

    return array_merge([
        'sede_id' => $espacio->piso->edificio->sede_id,
        'edificio_id' => $espacio->piso->edificio_id,
        'piso_id' => $espacio->piso_id,
        'espacio_fisico_id' => $espacio->id,
        'categoria_id' => $categoriaPrincipal->id,
        'subcategoria_id' => $categoriaPrincipal->subcategorias()->value('id'),
        'descripcion' => 'La chapa de la puerta principal del aula no cierra y queda abierta.',
    ], $extra);
}

it('radica la incidencia en E01 con consecutivo TKT-AAAA-###### y metadatos del servidor', function () {
    $respuesta = $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio))
        ->assertCreated()
        ->assertJsonPath('data.estado.codigo', 'E01');

    $anio = now()->setTimezone('America/Bogota')->year;
    expect($respuesta->json('data.codigo_ticket'))->toBe(sprintf('TKT-%d-000001', $anio));

    $incidencia = Incidencia::query()->findOrFail($respuesta->json('data.id'));
    expect($incidencia->usuario_creador_id)->toBe($this->reportante->id)
        ->and($incidencia->sede_id)->toBe($this->espacio->piso->edificio->sede_id)
        ->and($incidencia->canal_radicacion)->toBe('WEB')
        ->and(EventoAuditoria::query()->where('incidencia_id', $incidencia->id)->where('tipo_evento', 'CREACION')->exists())->toBeTrue();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio))
        ->assertJsonPath('data.codigo_ticket', sprintf('TKT-%d-000002', $anio));
});

it('no duplica el ticket ante envíos repetidos con la misma Idempotency-Key', function () {
    $peticion = fn () => $this->comoRol($this->reportante, Rol::Reportante)
        ->withHeader('Idempotency-Key', 'formulario-123')
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio));

    $primera = $peticion()->assertCreated();
    $peticion()->assertOk()->assertJsonPath('data.id', $primera->json('data.id'));

    expect(Incidencia::query()->count())->toBe(1);
});

it('valida la jerarquía obligatoria de 4 niveles y la longitud de la descripción', function () {
    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, extra: ['espacio_fisico_id' => null, 'descripcion' => 'Luz rota']))
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('errors.espacio_fisico_id.0', 'Debe especificar el aula o espacio específico (RN-003)')
        ->assertJsonPath('errors.descripcion.0', 'La descripción debe tener al menos 20 caracteres.');

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, extra: ['descripcion' => str_repeat('a', 501)]))
        ->assertJsonValidationErrors(['descripcion' => 'La descripción no puede superar los 500 caracteres.']);
});

it('rechaza espacios que no pertenecen al piso seleccionado o están inactivos', function () {
    $otroEspacio = EspacioFisico::factory()->create();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, extra: ['espacio_fisico_id' => $otroEspacio->id]))
        ->assertJsonValidationErrors('espacio_fisico_id');

    $this->espacio->update(['activo' => false]);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio))
        ->assertJsonValidationErrors(['espacio_fisico_id' => 'La ubicación seleccionada se encuentra inactiva.']);
});

it('exige evidencia fotográfica en categorías de daño estructural (RN-005) y la vincula al radicar', function () {
    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, 'ESTRUCTURAL_TECHOS'))
        ->assertJsonValidationErrors(['evidencias' => 'Para categorías de daño estructural es obligatorio adjuntar al menos una evidencia fotográfica (RN-005)']);

    $ids = $this->subirEvidencias($this->reportante, Rol::Reportante, 2);

    $id = $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, 'ESTRUCTURAL_TECHOS', ['evidencias' => $ids]))
        ->assertCreated()
        ->json('data.id');

    expect(Incidencia::query()->find($id)->evidencias()->count())->toBe(2);
});

it('no permite vincular evidencias cargadas por otro usuario', function () {
    $ajenas = $this->subirEvidencias(User::factory()->conRoles(Rol::Reportante)->create(), Rol::Reportante);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, extra: ['evidencias' => $ajenas]))
        ->assertJsonValidationErrors('evidencias');
});

it('rechaza archivos cuyo contenido real no es JPG, PNG, WEBP ni PDF aunque la extensión lo aparente', function () {
    $falso = UploadedFile::fake()->createWithContent('foto.jpg', "MZ\x90\x00 ejecutable disfrazado");

    $this->comoRol($this->reportante, Rol::Reportante)
        ->post('/api/v1/incidencias/evidencias/upload', ['archivos' => [$falso]], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['archivos.0' => ['Formato no permitido. Solo se aceptan imágenes JPG, PNG, WEBP o documentos PDF']]);
});

it('acepta PDF y WEBP reales y limita tamaño y cantidad de archivos', function () {
    $pdf = UploadedFile::fake()->createWithContent('acta.pdf', "%PDF-1.7\n%contenido");
    $webp = UploadedFile::fake()->createWithContent('foto.webp', 'RIFF'.pack('V', 20).'WEBPVP8 contenido');

    $this->comoRol($this->reportante, Rol::Reportante)
        ->post('/api/v1/incidencias/evidencias/upload', ['archivos' => [$pdf, $webp]], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.0.mime_type', 'application/pdf')
        ->assertJsonPath('data.1.mime_type', 'image/webp');

    $this->comoRol($this->reportante, Rol::Reportante)
        ->post('/api/v1/incidencias/evidencias/upload', ['archivos' => [UploadedFile::fake()->create('grande.png', 11 * 1024, 'image/png')]], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors(['archivos.0' => 'El archivo supera el tamaño máximo autorizado de 10 MB']);

    $cinco = array_map(fn () => $this->imagenPng(), range(1, 5));
    $this->comoRol($this->reportante, Rol::Reportante)
        ->post('/api/v1/incidencias/evidencias/upload', ['archivos' => $cinco], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors(['archivos' => 'Se ha alcanzado el límite máximo permitido de 4 archivos por reporte']);
});

it('advierte coincidencias activas en el mismo espacio sin bloquear la radicación y marca posible duplicado', function () {
    $existente = Incidencia::factory()->create(['espacio_fisico_id' => $this->espacio->id]);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/incidencias/coincidencias-activas?espacio_fisico_id={$this->espacio->id}&categoria_id={$existente->categoria_id}")
        ->assertOk()
        ->assertJsonPath('hay_coincidencias', true)
        ->assertJsonPath('data.0.codigo_ticket', $existente->codigo_ticket);

    $id = $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, extra: ['es_problema_diferente' => true]))
        ->assertCreated()
        ->assertJsonPath('data.posible_duplicado', true)
        ->json('data.id');

    expect(Incidencia::query()->count())->toBe(2)->and(Incidencia::query()->find($id)->codigo_ticket)->not->toBe($existente->codigo_ticket);
});

it('ignora coincidencias cerradas o con más de 15 días', function () {
    Incidencia::factory()->cerradaHace(1)->create(['espacio_fisico_id' => $this->espacio->id]);
    Incidencia::factory()->create(['espacio_fisico_id' => $this->espacio->id, 'created_at' => now()->subDays(20)]);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson("/api/v1/incidencias/coincidencias-activas?espacio_fisico_id={$this->espacio->id}")
        ->assertJsonPath('hay_coincidencias', false);
});

it('suscribe al reportante al seguimiento de una incidencia existente', function () {
    $existente = Incidencia::factory()->create(['espacio_fisico_id' => $this->espacio->id]);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson("/api/v1/incidencias/{$existente->id}/suscribir-seguimiento")
        ->assertOk()
        ->assertJsonPath('redirect_to', '/mis-solicitudes');

    expect($existente->seguidores()->whereKey($this->reportante->id)->exists())->toBeTrue();

    $this->comoRol($this->reportante, Rol::Reportante)
        ->getJson('/api/v1/mis-solicitudes?ambito=seguidas')
        ->assertJsonPath('data.0.id', $existente->id);
});

it('activa el protocolo de riesgo con prioridad crítica y alerta a coordinación al declarar peligro inminente', function () {
    $coordinador = $this->usuarioConRol(Rol::Coordinador);

    $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, extra: ['es_riesgo_inmediato' => true]))
        ->assertJsonValidationErrors(['descripcion_riesgo' => 'Debe describir brevemente el peligro inminente para activar el protocolo de seguridad']);

    $id = $this->comoRol($this->reportante, Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio, extra: ['es_riesgo_inmediato' => true, 'descripcion_riesgo' => 'Escape de gas en el laboratorio']))
        ->assertCreated()
        ->json('data.id');

    $incidencia = Incidencia::query()->with('prioridad')->find($id);
    expect($incidencia->prioridad->codigo)->toBe('CRITICA')
        ->and($incidencia->fecha_limite_resolucion)->not->toBeNull()
        ->and(NotificacionPlataforma::query()->where('usuario_destino_id', $coordinador->id)->where('tipo', 'RIESGO_INMEDIATO')->exists())->toBeTrue()
        ->and(EventoAuditoria::query()->where('incidencia_id', $id)->where('tipo_evento', 'RIESGO_INMEDIATO_DECLARADO')->exists())->toBeTrue();
});

it('impide radicar a roles sin permiso de creación (D09)', function () {
    $this->comoRol($this->usuarioConRol(Rol::Autorizador), Rol::Autorizador)
        ->postJson('/api/v1/incidencias/radicar', datosRadicacion($this->espacio))
        ->assertForbidden();
});
