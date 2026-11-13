<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Respuestas de error en formato Problem Details (RFC 7807).
 */
final class ProblemDetails
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function respuesta(Request $request, int $status, string $titulo, ?string $detalle = null, array $extra = []): JsonResponse
    {
        $cuerpo = array_merge([
            'type' => 'https://praxis.ecci.edu.co/problemas/'.self::slug($status),
            'title' => $titulo,
            'status' => $status,
            'detail' => $detalle,
            'instance' => '/'.ltrim($request->path(), '/'),
        ], $extra);

        return new JsonResponse(array_filter($cuerpo, fn ($valor) => $valor !== null), $status, [
            'Content-Type' => 'application/problem+json',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function slug(int $status): string
    {
        return match ($status) {
            400 => 'solicitud-invalida',
            401 => 'no-autenticado',
            403 => 'acceso-denegado',
            404 => 'recurso-no-encontrado',
            405 => 'metodo-no-permitido',
            409 => 'conflicto-de-estado',
            410 => 'enlace-expirado',
            422 => 'regla-de-negocio',
            423 => 'cuenta-bloqueada',
            429 => 'demasiadas-solicitudes',
            default => 'error-interno',
        };
    }
}
