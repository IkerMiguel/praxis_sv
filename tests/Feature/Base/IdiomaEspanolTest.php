<?php

use App\Enums\Rol;
use Illuminate\Support\Facades\URL;

/**
 * Falla si el cuerpo trae frases típicas de los mensajes en inglés de Laravel.
 */
function expectSinTextoEnIngles(string $cuerpo): void
{
    expect($cuerpo)->not->toMatch('/\b(The|field|must|more errors?|Unauthenticated|unauthorized|Invalid signature|Too Many|Not Found)\b/');
}

it('responde la validación 422 en español con nombres de campo legibles', function () {
    $respuesta = $this->comoRol($this->usuarioConRol(Rol::Reportante), Rol::Reportante)
        ->postJson('/api/v1/incidencias/radicar', ['sede_id' => 1, 'descripcion' => 'corta']);

    $respuesta->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Los datos enviados no son válidos.')
        ->assertJsonPath('errors.categoria_id.0', 'El campo categoría es obligatorio.')
        ->assertJsonPath('errors.descripcion.0', 'La descripción debe tener al menos 20 caracteres.');

    expect($respuesta->json('detail'))->toEndWith('errores más)');
    expectSinTextoEnIngles($respuesta->getContent());
});

it('usa los nombres legibles también en las validaciones de los controladores', function () {
    $this->comoRol($this->usuarioConRol(Rol::Coordinador), Rol::Coordinador)
        ->getJson('/api/v1/incidencias/coincidencias-activas')
        ->assertUnprocessable()
        ->assertJsonPath('errors.espacio_fisico_id.0', 'El campo espacio físico es obligatorio.');
});

it('responde el 401 en español', function () {
    $respuesta = $this->getJson('/api/v1/auth/me');

    $respuesta->assertUnauthorized()
        ->assertJsonPath('title', 'No autenticado.')
        ->assertJsonPath('detail', 'Debe iniciar sesión o su token expiró.');

    expectSinTextoEnIngles($respuesta->getContent());
});

it('responde en español los errores 403, 404, 405 y 429', function () {
    $reportante = $this->usuarioConRol(Rol::Reportante);

    $sinPermiso = $this->comoRol($reportante, Rol::Reportante)->getJson('/api/v1/consola/incidencias');
    $sinPermiso->assertForbidden()->assertJsonPath('title', 'Acceso denegado.');

    [$evidenciaId] = $this->subirEvidencias($reportante, Rol::Reportante);
    $firmaInvalida = $this->getJson(URL::temporarySignedRoute('evidencias.descargar', now()->addMinutes(5), ['evidencia' => $evidenciaId]).'x');
    $firmaInvalida->assertForbidden()->assertJsonPath('detail', 'El enlace no es válido o ya venció. Solicite uno nuevo.');

    $noExiste = $this->getJson('/api/v1/ruta-que-no-existe');
    $noExiste->assertNotFound()->assertJsonPath('title', 'Recurso no encontrado.');

    $metodo = $this->deleteJson('/api/v1/auth/login');
    $metodo->assertMethodNotAllowed()->assertJsonPath('title', 'Método no permitido.');

    foreach (range(1, 20) as $intento) {
        $this->postJson('/api/v1/auth/login', ['email' => 'nadie@praxis.test', 'password' => 'x']);
    }
    $limite = $this->postJson('/api/v1/auth/login', ['email' => 'nadie@praxis.test', 'password' => 'x']);
    $limite->assertTooManyRequests()->assertJsonPath('title', 'Demasiadas solicitudes.');

    foreach ([$sinPermiso, $firmaInvalida, $noExiste, $metodo, $limite] as $respuesta) {
        expectSinTextoEnIngles($respuesta->getContent());
    }
});
