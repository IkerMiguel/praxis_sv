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

    /**
     * Semáforo del plazo según el porcentaje consumido: en plazo por debajo del umbral, próxima a vencer
     * desde el umbral hasta el 100 % y vencida por encima del 100 % (HU-10.3).
     */
    public static function segunConsumo(float $porcentajeConsumido, int $umbral): self
    {
        return match (true) {
            $porcentajeConsumido > 100 => self::Vencida,
            $porcentajeConsumido >= $umbral => self::ProximaAVencer,
            default => self::EnPlazo,
        };
    }

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
