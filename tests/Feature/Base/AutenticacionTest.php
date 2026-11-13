<?php

use App\Enums\Rol;
use App\Models\EventoAuditoria;
use App\Models\TokenRecuperacion;
use App\Models\User;
use App\Notifications\RestablecerPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function crearUsuarioLogin(Rol $rol = Rol::Tecnico, array $atributos = []): User
{
    return User::factory()->conRoles($rol)->create(array_merge(['email' => 'tecnico@ecci.edu.co', 'password' => Hash::make('Clave*Segura1')], $atributos));
}

it('autentica, emite token de 15 minutos y refresh token HttpOnly y redirige al deep-link solicitado', function () {
    crearUsuarioLogin();

    $respuesta = $this->postJson('/api/v1/auth/login', [
        'email' => 'tecnico@ecci.edu.co',
        'password' => 'Clave*Segura1',
        'return_url' => '/mis-tareas/105',
    ]);

    $respuesta->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 900)
        ->assertJsonPath('redirect_to', '/mis-tareas/105')
        ->assertJsonPath('usuario.rol_activo.codigo', 'R04')
        ->assertCookie('praxis_refresh', null, false);

    expect($respuesta->getCookie('praxis_refresh', false)->isHttpOnly())->toBeTrue();
});

it('redirige a la ruta por defecto del rol cuando el returnUrl no es una ruta interna', function () {
    crearUsuarioLogin(Rol::Autorizador);

    $this->postJson('/api/v1/auth/login', ['email' => 'tecnico@ecci.edu.co', 'password' => 'Clave*Segura1', 'return_url' => '//sitio-malicioso.com'])
        ->assertOk()
        ->assertJsonPath('redirect_to', '/autorizaciones');
});

it('responde con un mensaje genérico ante correo inexistente o contraseña incorrecta y audita el fallo', function (string $email, string $password) {
    crearUsuarioLogin();

    $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('detail', 'Credenciales de acceso inválidas');

    expect(EventoAuditoria::query()->where('tipo_evento', 'LOGIN_FALLIDO')->count())->toBe(1);
})->with([
    'contraseña incorrecta' => ['tecnico@ecci.edu.co', 'otra-clave'],
    'correo no registrado' => ['nadie@ecci.edu.co', 'Clave*Segura1'],
]);

it('bloquea la cuenta 15 minutos tras 5 intentos fallidos consecutivos', function () {
    $usuario = crearUsuarioLogin();

    foreach (range(1, 5) as $intento) {
        $this->postJson('/api/v1/auth/login', ['email' => 'tecnico@ecci.edu.co', 'password' => 'incorrecta'])->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'tecnico@ecci.edu.co', 'password' => 'Clave*Segura1'])
        ->assertStatus(423)
        ->assertJsonPath('detail', 'Cuenta bloqueada temporalmente por seguridad. Intente más tarde');

    expect($usuario->refresh()->bloqueado_hasta->diffInMinutes(now(), true))->toBeGreaterThan(14);

    $this->travel(16)->minutes();

    $this->postJson('/api/v1/auth/login', ['email' => 'tecnico@ecci.edu.co', 'password' => 'Clave*Segura1'])->assertOk();
});

it('deniega el acceso a usuarios inactivos con credenciales correctas', function () {
    crearUsuarioLogin(atributos: ['activo' => false]);

    $this->postJson('/api/v1/auth/login', ['email' => 'tecnico@ecci.edu.co', 'password' => 'Clave*Segura1'])
        ->assertForbidden()
        ->assertJsonPath('detail', 'Usuario inactivo. Comuníquese con el administrador del sistema');
});

it('rota el refresh token y revoca la familia si se reutiliza un token ya usado', function () {
    crearUsuarioLogin();
    $login = $this->postJson('/api/v1/auth/login', ['email' => 'tecnico@ecci.edu.co', 'password' => 'Clave*Segura1']);
    $primero = $login->getCookie('praxis_refresh', false)->getValue();

    $refresco = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $primero])->assertOk();
    $segundo = $refresco->getCookie('praxis_refresh', false)->getValue();

    expect($segundo)->not->toBe($primero);

    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $primero])->assertUnauthorized();
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $segundo])->assertUnauthorized();
});

it('rechaza tokens de acceso expirados', function () {
    crearUsuarioLogin();
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'tecnico@ecci.edu.co', 'password' => 'Clave*Segura1'])->json('access_token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

    $this->travel(16)->minutes();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('restablece la contraseña con un enlace de un solo uso sin revelar si el correo existe', function () {
    Notification::fake();
    $usuario = crearUsuarioLogin();
    $mensaje = 'Si el correo se encuentra registrado, hemos enviado las instrucciones a su bandeja de entrada';

    $this->postJson('/api/v1/auth/solicitar-recuperacion', ['email' => 'nadie@ecci.edu.co'])->assertOk()->assertJsonPath('mensaje', $mensaje);
    $this->postJson('/api/v1/auth/solicitar-recuperacion', ['email' => 'tecnico@ecci.edu.co'])->assertOk()->assertJsonPath('mensaje', $mensaje);

    $token = null;
    Notification::assertSentTo($usuario, RestablecerPasswordNotification::class, function (RestablecerPasswordNotification $notificacion) use (&$token) {
        $token = $notificacion->token;

        return true;
    });
    Notification::assertCount(1);

    $this->getJson("/api/v1/auth/restablecer-password/{$token}")->assertOk()->assertJsonPath('valido', true);

    $this->postJson('/api/v1/auth/restablecer-password', ['token' => $token, 'password' => 'debil', 'password_confirmation' => 'debil'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->postJson('/api/v1/auth/restablecer-password', ['token' => $token, 'password' => 'Nueva*Clave9', 'password_confirmation' => 'Nueva*Clave9'])
        ->assertOk()
        ->assertJsonPath('redirect_to', '/login');

    expect(Hash::check('Nueva*Clave9', $usuario->refresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/restablecer-password', ['token' => $token, 'password' => 'Otra*Clave9', 'password_confirmation' => 'Otra*Clave9'])
        ->assertStatus(410)
        ->assertJsonPath('type', 'https://praxis.ecci.edu.co/problemas/enlace-expirado')
        ->assertJsonPath('detail', 'El enlace de restablecimiento ha expirado. Por favor solicite uno nuevo');
});

it('bloquea el enlace de recuperación vencido a los 30 minutos', function () {
    Notification::fake();
    crearUsuarioLogin();
    $this->postJson('/api/v1/auth/solicitar-recuperacion', ['email' => 'tecnico@ecci.edu.co']);
    $token = Notification::sent(User::first(), RestablecerPasswordNotification::class)->first()->token;

    $this->travel(45)->minutes();

    $this->getJson("/api/v1/auth/restablecer-password/{$token}")->assertStatus(410);
    expect(TokenRecuperacion::query()->value('token_hash'))->toBe(hash('sha256', $token));
});

it('permite al cliente web enviar la cookie de refresh desde su origen (CORS con credenciales)', function () {
    $origen = config('praxis.frontend_url');

    $this->call('OPTIONS', '/api/v1/auth/refresh', server: [
        'HTTP_ORIGIN' => $origen,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ])
        ->assertHeader('Access-Control-Allow-Origin', $origen)
        ->assertHeader('Access-Control-Allow-Credentials', 'true');

    $ajeno = $this->call('OPTIONS', '/api/v1/auth/refresh', server: [
        'HTTP_ORIGIN' => 'https://sitio-ajeno.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($ajeno->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://sitio-ajeno.com');
});
