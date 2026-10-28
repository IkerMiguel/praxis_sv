<?php

namespace App\Enums;

/**
 * Semáforo de cumplimiento de plazos (HU-10.3).
 */
enum EstadoSla: string
{
    case SinPlazo = 'SIN_PLAZO';
    case EnPlazo = 'EN_PLAZO';
    case ProximaAVencer = 'PROXIMA_A_VENCER';
    case Vencida = 'VENCIDA';
    case Cumplida = 'CUMPLIDA';
    case Incumplida = 'INCUMPLIDA';

    public function color(): string
    {
        return match ($this) {
            self::EnPlazo, self::Cumplida => 'verde',
            self::ProximaAVencer => 'amarillo',
            self::Vencida, self::Incumplida => 'rojo',
            self::SinPlazo => 'gris',
        };
    }
}
