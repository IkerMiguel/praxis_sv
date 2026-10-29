<?php

namespace App\Http\Middleware;

use App\Enums\CategoriaEvento;
use App\Services\AuditoriaService;
use App\Support\ContextoRol;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivalente a RequireRoleAttribute: exige que el rol activo esté entre los autorizados (D09).
 * Uso: ->middleware('rol:R02,R07'). Los rechazos quedan en la bitácora de seguridad.
 */
class RequiereRol
{
    public function __construct(private ContextoRol $contextoRol, private AuditoriaService $auditoria) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $activo = $this->contextoRol->codigo();

        if ($activo === null || ! in_array($activo, $roles, true)) {
            $this->auditoria->registrar(
                tipoEvento: 'ACCESO_DENEGADO',
                categoria: CategoriaEvento::Seguridad,
                usuario: $request->user(),
                motivo: 'Intento de acceso a una operación no autorizada para el rol activo.',
                datos: ['ruta' => $request->path(), 'metodo' => $request->method(), 'roles_requeridos' => $roles],
            );

            throw new AuthorizationException('Su rol activo no tiene permisos para esta operación.');
        }

        return $next($request);
    }
}
