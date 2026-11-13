<?php

namespace App\Enums;

enum EstadoSolicitudRecursos: string
{
    case PendienteAutorizacion = 'PENDIENTE_AUTORIZACION';
    case PendienteRecursos = 'PENDIENTE_RECURSOS';
    case Disponible = 'DISPONIBLE';
    case Rechazada = 'RECHAZADA';
    case Cancelada = 'CANCELADA';
}
