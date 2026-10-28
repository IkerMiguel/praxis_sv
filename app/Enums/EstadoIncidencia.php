<?php

namespace App\Enums;

/**
 * Los 18 estados del ciclo de vida de una incidencia (D07).
 */
enum EstadoIncidencia: string
{
    case Registrada = 'E01';
    case PendienteInformacion = 'E02';
    case EnRevision = 'E03';
    case EnClasificacion = 'E04';
    case PendienteAsignacion = 'E05';
    case PendienteInspeccion = 'E06';
    case EnDiagnostico = 'E07';
    case PendienteAutorizacion = 'E08';
    case PendienteRecursos = 'E09';
    case Programada = 'E10';
    case EnAtencion = 'E11';
    case PendienteVerificacion = 'E12';
    case EnCorreccion = 'E13';
    case PendienteConfirmacion = 'E14';
    case Cerrada = 'E15';
    case NoProcedente = 'E16';
    case Cancelada = 'E17';
    case Suspendida = 'E18';

    public function nombre(): string
    {
        return match ($this) {
            self::Registrada => 'Registrada',
            self::PendienteInformacion => 'Pendiente de información',
            self::EnRevision => 'En revisión',
            self::EnClasificacion => 'En clasificación',
            self::PendienteAsignacion => 'Pendiente de asignación',
            self::PendienteInspeccion => 'Pendiente de inspección',
            self::EnDiagnostico => 'En diagnóstico',
            self::PendienteAutorizacion => 'Pendiente de autorización',
            self::PendienteRecursos => 'Pendiente de recursos',
            self::Programada => 'Programada',
            self::EnAtencion => 'En atención',
            self::PendienteVerificacion => 'Pendiente de verificación',
            self::EnCorreccion => 'En corrección',
            self::PendienteConfirmacion => 'Pendiente de confirmación',
            self::Cerrada => 'Cerrada',
            self::NoProcedente => 'No procedente',
            self::Cancelada => 'Cancelada',
            self::Suspendida => 'Suspendida',
        };
    }

    public function esTerminal(): bool
    {
        return in_array($this, [self::Cerrada, self::NoProcedente, self::Cancelada], true);
    }

    /**
     * Estados considerados "activos" para la detección de duplicados (E01 a E11, RN-011).
     *
     * @return list<self>
     */
    public static function activosParaDuplicados(): array
    {
        return [
            self::Registrada, self::PendienteInformacion, self::EnRevision, self::EnClasificacion,
            self::PendienteAsignacion, self::PendienteInspeccion, self::EnDiagnostico, self::PendienteAutorizacion,
            self::PendienteRecursos, self::Programada, self::EnAtencion,
        ];
    }

    /**
     * Estados que cuentan como carga de trabajo (WIP) del técnico (HU-6.2).
     *
     * @return list<self>
     */
    public static function cargaTecnico(): array
    {
        return [self::PendienteInspeccion, self::EnDiagnostico, self::EnAtencion, self::EnCorreccion];
    }

    /**
     * @return list<string>
     */
    public static function valores(array $estados): array
    {
        return array_map(fn (self $estado) => $estado->value, $estados);
    }
}
