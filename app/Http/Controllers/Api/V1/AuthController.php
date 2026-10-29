<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RestablecerPasswordRequest;
use App\Http\Requests\SolicitarRecuperacionRequest;
use App\Models\User;
use App\Services\AutenticacionService;
use App\Services\PerfilService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function __construct(private AutenticacionService $autenticacion, private PerfilService $perfil) {}

    /**
     * POST /api/v1/auth/login (HU-1.1).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $sesion = $this->autenticacion->iniciarSesion(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->respuestaSesion($sesion, $this->autenticacion->rutaPostLogin($sesion['usuario'], $request->input('return_url')));
    }

    /**
     * POST /api/v1/auth/refresh: rota el refresh token (cookie HttpOnly o campo refresh_token).
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $this->refreshTokenDe($request);

        if ($token === null) {
            throw new AuthenticationException('No se encontró el refresh token.');
        }

        $sesion = $this->autenticacion->refrescar($token, $request->ip(), $request->userAgent());

        return $this->respuestaSesion($sesion, null);
    }

    /**
     * POST /api/v1/auth/logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->autenticacion->cerrarSesion($request->user(), $this->refreshTokenDe($request));

        return response()->json(['mensaje' => 'Sesión cerrada correctamente.'])
            ->withCookie($this->cookieRefresh('', -1));
    }

    /**
     * GET /api/v1/auth/me: hidrata el perfil y el rol activo del shell (HU-2.1).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->perfil->perfil($request->user())]);
    }

    /**
     * POST /api/v1/auth/solicitar-recuperacion (HU-1.4). Respuesta idéntica exista o no el correo.
     */
    public function solicitarRecuperacion(SolicitarRecuperacionRequest $request): JsonResponse
    {
        $this->autenticacion->solicitarRecuperacion($request->string('email')->toString());

        return response()->json([
            'mensaje' => 'Si el correo se encuentra registrado, hemos enviado las instrucciones a su bandeja de entrada',
        ]);
    }

    /**
     * GET /api/v1/auth/restablecer-password/{token}: valida el enlace antes de mostrar el formulario.
     */
    public function validarEnlace(string $token): JsonResponse
    {
        if ($this->autenticacion->tokenRecuperacionVigente($token) === null) {
            return response()->json([
                'type' => 'https://praxis.ecci.edu.co/problemas/enlace-expirado',
                'title' => 'Enlace inválido.',
                'status' => 410,
                'detail' => AutenticacionService::MENSAJE_ENLACE_INVALIDO,
            ], 410, ['Content-Type' => 'application/problem+json']);
        }

        return response()->json(['valido' => true]);
    }

    /**
     * POST /api/v1/auth/restablecer-password (HU-1.4).
     */
    public function restablecerPassword(RestablecerPasswordRequest $request): JsonResponse
    {
        $this->autenticacion->restablecerPassword(
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['mensaje' => 'Contraseña actualizada correctamente. Inicie sesión con su nueva clave.', 'redirect_to' => '/login']);
    }

    /**
     * @param  array{usuario: User, access_token: string, refresh_token: string}  $sesion
     */
    private function respuestaSesion(array $sesion, ?string $redirectTo): JsonResponse
    {
        $usuario = $sesion['usuario'];
        $rolInicial = isset($usuario->codigosRol()[0]) ? Rol::from($usuario->codigosRol()[0]) : null;

        return response()->json([
            'access_token' => $sesion['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => (int) config('praxis.access_token_minutos') * 60,
            'refresh_expires_in' => (int) config('praxis.refresh_token_dias') * 86400,
            'redirect_to' => $redirectTo,
            'usuario' => $this->perfil->perfil($usuario, $rolInicial),
        ])->withCookie($this->cookieRefresh($sesion['refresh_token'], (int) config('praxis.refresh_token_dias') * 1440));
    }

    private function refreshTokenDe(Request $request): ?string
    {
        $token = $request->cookie(config('praxis.refresh_cookie.nombre')) ?? $request->input('refresh_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function cookieRefresh(string $valor, int $minutos): Cookie
    {
        $config = config('praxis.refresh_cookie');

        return cookie(
            $config['nombre'],
            $valor,
            $minutos,
            $config['path'],
            $config['dominio'],
            $config['secure'],
            true,
            false,
            $config['same_site'],
        );
    }
}
