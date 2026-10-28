<?php

use App\Enums\Rol;
use App\Models\EventoAuditoria;

it('muestra al reportante solo Inicio, Nuevo reporte, Mis solicitudes y Ayuda', function () {
    $this->comoRol($this->usuarioConRol(Rol::Reportante), Rol::Reportante)
        ->getJson('/api/v1/navegacion/menu')
        ->assertOk()
        ->assertJsonPath('rol_activo', 'R01')
        ->assertJsonPath('data.*.etiqueta', ['Inicio', 'Nuevo reporte', 'Mis solicitudes', 'Ayuda']);
});

it('conmuta el menú en caliente para un usuario multi-rol según X-Active-Role', function () {
    $usuario = $this->usuarioConRol(Rol::Reportante, Rol::Verificador);

    $this->comoRol($usuario, Rol::Verificador)
        ->getJson('/api/v1/navegacion/menu')
        ->assertOk()
        ->assertJsonPath('rol_activo', 'R06')
        ->assertJsonFragment(['ruta' => '/verificacion']);

    $this->comoRol($usuario, Rol::Verificador)
        ->getJson('/api/v1/auth/me')
        ->assertJsonPath('data.rol_activo.codigo', 'R06')
        ->assertJsonCount(2, 'data.roles');
});

it('rechaza un X-Active-Role que no está asignado al usuario', function () {
    $this->comoRol($this->usuarioConRol(Rol::Reportante), Rol::Administrador)
        ->getJson('/api/v1/navegacion/menu')
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect(EventoAuditoria::query()->where('tipo_evento', 'ROL_ACTIVO_NO_ASIGNADO')->exists())->toBeTrue();
});

it('responde 403 y audita el intento cuando el rol activo no tiene permiso sobre la operación', function () {
    $tecnico = $this->usuarioConRol(Rol::Tecnico);

    $this->comoRol($tecnico, Rol::Tecnico)
        ->postJson('/api/v1/solicitudes-recursos/1/dictamen', ['resultado' => 'APROBADA'])
        ->assertForbidden()
        ->assertJsonPath('status', 403);

    expect(EventoAuditoria::query()->where('tipo_evento', 'ACCESO_DENEGADO')->where('usuario_id', $tecnico->id)->exists())->toBeTrue();
});

it('exige autenticación en los endpoints protegidos', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('title', 'No autenticado.');
});
