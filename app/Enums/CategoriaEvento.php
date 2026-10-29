<?php

namespace App\Enums;

/**
 * Los cuatro conceptos de trazabilidad de D10 más las comunicaciones y la seguridad.
 */
enum CategoriaEvento: string
{
    case Estado = 'ESTADO';
    case Actividad = 'ACTIVIDAD';
    case Forense = 'FORENSE';
    case Comunicacion = 'COMUNICACION';
    case Seguridad = 'SEGURIDAD';
    case Configuracion = 'CONFIGURACION';
}
