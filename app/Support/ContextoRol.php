<?php

namespace App\Support;

use App\Enums\Rol;

/**
 * Rol activo de la petición actual, resuelto desde la cabecera X-Active-Role (HU-1.3).
 * Registrado como servicio "scoped": vive solo durante la petición.
 */
class ContextoRol
{
    private ?Rol $rolActivo = null;

    public function establecer(Rol $rol): void
    {
        $this->rolActivo = $rol;
    }

    public function rol(): ?Rol
    {
        return $this->rolActivo;
    }

    public function codigo(): ?string
    {
        return $this->rolActivo?->value;
    }

    public function es(Rol ...$roles): bool
    {
        return $this->rolActivo !== null && in_array($this->rolActivo, $roles, true);
    }
}
