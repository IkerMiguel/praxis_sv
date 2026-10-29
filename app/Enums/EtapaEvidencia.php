<?php

namespace App\Enums;

enum EtapaEvidencia: string
{
    case Reporte = 'REPORTE';
    case Aclaracion = 'ACLARACION';
    case Inspeccion = 'INSPECCION';
    case Ejecucion = 'EJECUCION';
    case Verificacion = 'VERIFICACION';
}
