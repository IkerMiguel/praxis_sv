<?php

use App\Exceptions\ReglaNegocioException;
use App\Http\Middleware\RequiereRol;
use App\Http\Middleware\ResolverRolActivo;
use App\Support\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => RequiereRol::class,
            'rol.activo' => ResolverRolActivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([ReglaNegocioException::class]);

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $exception instanceof ValidationException => ProblemDetails::respuesta(
                    $request, 422, 'Los datos enviados no son válidos.', $exception->getMessage(), ['errors' => $exception->errors()],
                ),
                $exception instanceof ReglaNegocioException => ProblemDetails::respuesta(
                    $request, $exception->status, $exception->status === 409 ? 'Conflicto con el estado actual.' : 'Regla de negocio incumplida.',
                    $exception->getMessage(), array_merge(['regla' => $exception->regla], $exception->extra),
                ),
                $exception instanceof AuthenticationException => ProblemDetails::respuesta(
                    $request, 401, 'No autenticado.',
                    $exception->getMessage() !== 'Unauthenticated.' ? $exception->getMessage() : 'Debe iniciar sesión o su token expiró.',
                ),
                $exception instanceof AuthorizationException, $exception instanceof AccessDeniedHttpException => ProblemDetails::respuesta(
                    $request, 403, 'Acceso denegado.', $exception->getMessage() ?: 'No tiene permisos para esta operación.',
                ),
                $exception instanceof ModelNotFoundException, $exception instanceof NotFoundHttpException => ProblemDetails::respuesta(
                    $request, 404, 'Recurso no encontrado.', 'El recurso solicitado no existe o no está disponible.',
                ),
                $exception instanceof MethodNotAllowedHttpException => ProblemDetails::respuesta(
                    $request, 405, 'Método no permitido.', 'La operación solicitada no está permitida sobre este recurso.',
                ),
                $exception instanceof ThrottleRequestsException => ProblemDetails::respuesta(
                    $request, 429, 'Demasiadas solicitudes.', 'Ha superado el número de intentos permitidos. Intente más tarde.',
                ),
                $exception instanceof HttpExceptionInterface => ProblemDetails::respuesta(
                    $request, $exception->getStatusCode(), 'Error en la solicitud.', $exception->getMessage() ?: null,
                ),
                default => config('app.debug') ? null : ProblemDetails::respuesta(
                    $request, 500, 'Error interno del servidor.', 'Ocurrió un error inesperado. Intente nuevamente.',
                ),
            };
        });
    })->create();
