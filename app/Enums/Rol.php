<?php

namespace App\Enums;

/**
 * Roles funcionales del proceso (D03).
 */
enum Rol: string
{
    case Reportante = 'R01';
    case Gestor = 'R02';
    case Coordinador = 'R03';
    case Tecnico = 'R04';
    case Autorizador = 'R05';
    case Verificador = 'R06';
    case Administrador = 'R07';

    public function nombre(): string
    {
        return match ($this) {
            self::Reportante => 'Reportante',
            self::Gestor => 'Gestor de incidencias',
            self::Coordinador => 'Coordinador de mantenimiento',
            self::Tecnico => 'Técnico de mantenimiento',
            self::Autorizador => 'Responsable de autorizaciones',
            self::Verificador => 'Verificador de intervenciones',
            self::Administrador => 'Administrador de plataforma',
        };
    }

    /**
     * Ruta por defecto tras el login según el rol (HU-1.1).
     */
    public function rutaInicio(): string
    {
        return match ($this) {
            self::Reportante => '/panel',
            self::Gestor => '/revision',
            self::Coordinador => '/planificacion',
            self::Tecnico => '/mis-tareas',
            self::Autorizador => '/autorizaciones',
            self::Verificador => '/verificacion',
            self::Administrador => '/admin/usuarios',
        };
    }
}
