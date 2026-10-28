<?php

namespace App\Enums;

enum CodigoPrioridad: string
{
    case Critica = 'CRITICA';
    case Alta = 'ALTA';
    case Media = 'MEDIA';
    case Baja = 'BAJA';
}
