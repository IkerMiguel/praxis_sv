<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\TokenRecuperacion;
use App\Models\TokenRefresco;
use App\Models\User;
use App\Notifications\RestablecerPasswordNotification;
use App\Support\Parametro;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Inicio de sesión con bloqueo por fuerza bruta (HU-1.1), tokens de acceso de corta duración con
 * refresh token rotativo (D15) y recuperación de contraseña con tokens SHA-256 (HU-1.4).
 */
class AutenticacionService
{
    public const MENSAJE_CREDENCIALES = 'Credenciales de acceso inválidas';

    public const MENSAJE_BLOQUEO = 'Cuenta bloqueada temporalmente por seguridad. Intente más tarde';

    public const MENSAJE_INACTIVO = 'Usuario inactivo. Comuníquese con el administrador del sistema';

    public const MENSAJE_ENLACE_INVALIDO = 'El enlace de restablecimiento ha expirado. Por favor solicite uno nuevo';

    public function __construct(private ParametrosService $parametros, private AuditoriaService $auditoria) {}

    /**
     * @return array{usuario: User, access_token: string, refresh_token: string}
     *
     * @throws AuthenticationException
     */
    public function iniciarSesion(string $email, string $password, ?string $ip, ?string $userAgent): array
    {
        $usuario = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

        if ($usuario === null) {
            Hash::check($password, '$2y$12$'.str_repeat('a', 53));
            $this->auditarFallo(null, $email, 'USUARIO_INEXISTENTE');

            throw new AuthenticationException(self::MENSAJE_CREDENCIALES);
        }

        if ($usuario->estaBloqueado()) {
            $this->auditarFallo($usuario, $email, 'CUENTA_BLOQUEADA');

            throw new ReglaNegocioException(self::MENSAJE_BLOQUEO, 'HU-1.1', 423, [
                'bloqueado_hasta' => $usuario->bloqueado_hasta->toIso8601String(),
            ]);
        }

        if (! Hash::check($password, $usuario->password)) {
            $this->registrarIntentoFallido($usuario);
            $this->auditarFallo($usuario, $email, 'PASSWORD_INCORRECTO');

            throw new AuthenticationException(self::MENSAJE_CREDENCIALES);
        }

        if (! $usuario->activo) {
            $this->auditarFallo($usuario, $email, 'USUARIO_INACTIVO');

            throw new ReglaNegocioException(self::MENSAJE_INACTIVO, 'HU-1.1', 403);
        }

        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'ultimo_intento_fallido_en' => null,
            'bloqueado_hasta' => null,
            'ultimo_acceso_en' => now(),
        ])->save();

        $this->auditoria->registrar(
            tipoEvento: 'LOGIN_EXITOSO',
            categoria: CategoriaEvento::Seguridad,
            usuario: $usuario,
            entidadTipo: 'usuario',
            entidadId: $usuario->id,
            rolCodigo: $usuario->codigosRol()[0] ?? null,
        );

        return [
            'usuario' => $usuario,
            'access_token' => $this->emitirAccessToken($usuario),
            'refresh_token' => $this->emitirRefreshToken($usuario, (string) Str::uuid(), $ip, $userAgent),
        ];
    }

    /**
     * Rota el refresh token: el presentado queda usado y se emite uno nuevo de la misma familia.
     * La reutilización de un token ya usado revoca toda la familia (posible robo).
     *
     * @return array{usuario: User, access_token: string, refresh_token: string}
     *
     * @throws AuthenticationException
     */
    public function refrescar(string $refreshToken, ?string $ip, ?string $userAgent): array
    {
        $resultado = DB::transaction(function () use ($refreshToken, $ip, $userAgent): array|string {
            $registro = TokenRefresco::query()
                ->where('token_hash', hash('sha256', $refreshToken))
                ->lockForUpdate()
                ->first();

            if ($registro === null) {
                return 'Refresh token inválido.';
            }

            if ($registro->usado_en !== null || $registro->revocado_en !== null) {
                TokenRefresco::query()->where('familia', $registro->familia)->whereNull('revocado_en')->update(['revocado_en' => now()]);

                return 'Refresh token reutilizado o revocado. Inicie sesión nuevamente.';
            }

            if ($registro->expira_en->isPast()) {
                return 'La sesión expiró. Inicie sesión nuevamente.';
            }

            $usuario = $registro->usuario;

            if (! $usuario->activo) {
                return self::MENSAJE_INACTIVO;
            }

            $registro->forceFill(['usado_en' => now(), 'revocado_en' => now()])->save();

            return [
                'usuario' => $usuario,
                'access_token' => $this->emitirAccessToken($usuario),
                'refresh_token' => $this->emitirRefreshToken($usuario, $registro->familia, $ip, $userAgent),
            ];
        });

        if (is_string($resultado)) {
            throw new AuthenticationException($resultado);
        }

        return $resultado;
    }

    public function cerrarSesion(User $usuario, ?string $refreshToken): void
    {
        $usuario->currentAccessToken()?->delete();

        if ($refreshToken !== null) {
            $registro = TokenRefresco::query()->where('token_hash', hash('sha256', $refreshToken))->first();

            if ($registro !== null && (int) $registro->user_id === (int) $usuario->id) {
                TokenRefresco::query()->where('familia', $registro->familia)->whereNull('revocado_en')->update(['revocado_en' => now()]);
            }
        }

        $this->auditoria->registrar(tipoEvento: 'LOGOUT', categoria: CategoriaEvento::Seguridad, usuario: $usuario);
    }

    /**
     * Genera un token de un solo uso y envía el enlace. Si el correo no existe no hace nada (anti-enumeración).
     */
    public function solicitarRecuperacion(string $email): void
    {
        $usuario = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

        if ($usuario === null || ! $usuario->activo) {
            return;
        }

        $token = Str::random(64);

        TokenRecuperacion::query()->where('user_id', $usuario->id)->where('usado', false)->update(['usado' => true, 'fecha_uso' => now()]);

        TokenRecuperacion::create([
            'user_id' => $usuario->id,
            'token_hash' => hash('sha256', $token),
            'fecha_expiracion' => now()->addMinutes($this->parametros->entero(Parametro::RECUPERACION_EXPIRACION_MINUTOS)),
            'usado' => false,
            'created_at' => now(),
        ]);

        $usuario->notify(new RestablecerPasswordNotification($token));

        $this->auditoria->registrar(
            tipoEvento: 'RECUPERACION_SOLICITADA',
            categoria: CategoriaEvento::Seguridad,
            usuario: $usuario,
            rolCodigo: $usuario->codigosRol()[0] ?? null,
        );
    }

    public function tokenRecuperacionVigente(string $token): ?TokenRecuperacion
    {
        $registro = TokenRecuperacion::query()->where('token_hash', hash('sha256', $token))->first();

        if ($registro === null || $registro->usado || $registro->fecha_expiracion->isPast()) {
            return null;
        }

        return $registro;
    }

    public function restablecerPassword(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password): void {
            $registro = $this->tokenRecuperacionVigente($token);

            if ($registro === null) {
                throw new ReglaNegocioException(self::MENSAJE_ENLACE_INVALIDO, 'HU-1.4', 410);
            }

            $usuario = $registro->usuario;
            $usuario->forceFill([
                'password' => $password,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            $registro->forceFill(['usado' => true, 'fecha_uso' => now()])->save();
            $usuario->revocarSesiones();

            $this->auditoria->registrar(
                tipoEvento: 'PASSWORD_RESTABLECIDO',
                categoria: CategoriaEvento::Seguridad,
                usuario: $usuario,
                rolCodigo: $usuario->codigosRol()[0] ?? null,
            );
        });
    }

    /**
     * Ruta posterior al login: deep-link solicitado (si es una ruta interna) o la ruta por defecto del rol.
     */
    public function rutaPostLogin(User $usuario, ?string $returnUrl): string
    {
        if (self::esRutaInterna($returnUrl)) {
            return $returnUrl;
        }

        $codigo = $usuario->codigosRol()[0] ?? Rol::Reportante->value;

        return Rol::from($codigo)->rutaInicio();
    }

    /**
     * Solo se acepta como deep-link una ruta interna del front ("/algo"), nunca "//host" ni URLs
     * absolutas, para evitar redirecciones abiertas (HU-1.1).
     */
    public static function esRutaInterna(?string $ruta): bool
    {
        return $ruta !== null && preg_match('#^/(?!/)[^\s\\\\]*$#', $ruta) === 1;
    }

    private function emitirAccessToken(User $usuario): string
    {
        return $usuario->createToken('praxis-api', ['*'], now()->addMinutes((int) config('praxis.access_token_minutos')))->plainTextToken;
    }

    private function emitirRefreshToken(User $usuario, string $familia, ?string $ip, ?string $userAgent): string
    {
        $token = Str::random(80);

        TokenRefresco::create([
            'user_id' => $usuario->id,
            'familia' => $familia,
            'token_hash' => hash('sha256', $token),
            'expira_en' => now()->addDays((int) config('praxis.refresh_token_dias')),
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 250, ''),
        ]);

        return $token;
    }

    private function registrarIntentoFallido(User $usuario): void
    {
        $ventana = $this->parametros->entero(Parametro::LOGIN_VENTANA_MINUTOS);
        $maximo = $this->parametros->entero(Parametro::LOGIN_MAXIMO_INTENTOS);
        $fueraDeVentana = $usuario->ultimo_intento_fallido_en === null
            || $usuario->ultimo_intento_fallido_en->lt(now()->subMinutes($ventana));

        $intentos = $fueraDeVentana ? 1 : $usuario->intentos_fallidos + 1;

        $usuario->forceFill([
            'intentos_fallidos' => $intentos,
            'ultimo_intento_fallido_en' => now(),
            'bloqueado_hasta' => $intentos >= $maximo ? now()->addMinutes($this->parametros->entero(Parametro::LOGIN_BLOQUEO_MINUTOS)) : null,
        ])->save();
    }

    private function auditarFallo(?User $usuario, string $email, string $causa): void
    {
        $this->auditoria->registrar(
            tipoEvento: 'LOGIN_FALLIDO',
            categoria: CategoriaEvento::Seguridad,
            usuario: $usuario,
            motivo: $causa,
            datos: ['email' => Str::lower($email)],
            rolCodigo: $usuario?->codigosRol()[0] ?? null,
        );
    }
}
