<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violación de una regla de negocio (RN-xxx). Se renderiza como Problem Details (RFC 7807).
 */
class ReglaNegocioException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        string $mensaje,
        public readonly ?string $regla = null,
        public readonly int $status = 422,
        public readonly array $extra = [],
    ) {
        parent::__construct($mensaje);
    }

    public static function conflicto(string $mensaje, ?string $regla = null): self
    {
        return new self($mensaje, $regla, 409);
    }
}
