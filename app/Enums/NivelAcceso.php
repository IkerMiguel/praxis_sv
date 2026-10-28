<?php

namespace App\Enums;

/**
 * Niveles de acceso de la matriz de permisos D09.
 */
enum NivelAcceso: string
{
    case Total = 'TOTAL';
    case Lectura = 'LECTURA';
    case Asignadas = 'ASIGNADAS';
}
