<?php

use App\Enums\CategoriaEvento;
use App\Enums\Rol;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('bloquea UPDATE y DELETE sobre la bitácora a nivel de modelo y de base de datos', function () {
    $evento = app(AuditoriaService::class)->registrar('PRUEBA', CategoriaEvento::Seguridad);

    expect(fn () => $evento->update(['motivo' => 'alterado']))->toThrow(LogicException::class)
        ->and(fn () => $evento->delete())->toThrow(LogicException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('eventos_auditoria')->where('id', $evento->id)->update(['motivo' => 'x'])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('eventos_auditoria')->delete()))->toThrow(QueryException::class);

    expect(EventoAuditoria::query()->count())->toBe(1);
});

it('responde 405 ante intentos de modificar o borrar el historial por la API', function (string $metodo) {
    $incidencia = Incidencia::factory()->create();

    $this->comoRol($this->usuarioConRol(Rol::Administrador), Rol::Administrador)
        ->json($metodo, "/api/v1/incidencias/{$incidencia->id}/historial")
        ->assertStatus(405)
        ->assertJsonPath('title', 'Método no permitido.');
})->with(['PUT', 'PATCH', 'DELETE']);
