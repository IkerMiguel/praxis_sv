<?php

namespace App\Http\Middleware;

use App\Enums\CategoriaEvento;
use App\Enums\Rol;
use App\Services\AuditoriaService;
use App\Support\ContextoRol;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el rol activo desde la cabecera X-Active-Role y lo valida contra los roles del usuario
 * autenticado por el token (HU-1.3). Sin cabecera, se usa el primer rol asignado según la jerarquía.
 */
class ResolverRolActivo
{
    public const CABECERA = 'X-Active-Role';

    public function __construct(private ContextoRol $contextoRol, private AuditoriaService $auditoria) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return $next($request);
        }

        $codigos = $usuario->codigosRol();
        $solicitado = $request->header(self::CABECERA);

        if ($solicitado !== null && $solicitado !== '') {
            $solicitado = strtoupper(trim($solicitado));

            if (! in_array($solicitado, $codigos, true)) {
                $this->auditoria->registrar(
                    tipoEvento: 'ROL_ACTIVO_NO_ASIGNADO',
                    categoria: CategoriaEvento::Seguridad,
                    usuario: $usuario,
                    motivo: "Cabecera X-Active-Role con rol no asignado: {$solicitado}",
                    datos: ['ruta' => $request->path(), 'metodo' => $request->method()],
                    rolCodigo: $solicitado,
                );

                throw new AuthorizationException('El rol activo enviado en X-Active-Role no está asignado al usuario.');
            }

            $this->contextoRol->establecer(Rol::from($solicitado));
        } elseif ($codigos !== []) {
            $this->contextoRol->establecer(Rol::from($codigos[0]));
        }

        return $next($request);
    }
}
