<?php

namespace App\Support;

/**
 * Claves de los parámetros del sistema (HU-11.2, HU-11.3).
 */
final class Parametro
{
    public const LIMITE_WIP_TECNICO = 'LIMITE_WIP_TECNICO';

    public const PLAZO_ACLARACION_DIAS_HABILES = 'PLAZO_ACLARACION_DIAS_HABILES';

    public const VENTANA_REAPERTURA_DIAS_HABILES = 'VENTANA_REAPERTURA_DIAS_HABILES';

    public const MAXIMO_REAPERTURAS = 'MAXIMO_REAPERTURAS';

    public const PLAZO_CONFIRMACION_DIAS_HABILES = 'PLAZO_CONFIRMACION_DIAS_HABILES';

    public const VENTANA_DUPLICADOS_DIAS = 'VENTANA_DUPLICADOS_DIAS';

    public const MAX_EVIDENCIAS_POR_REPORTE = 'MAX_EVIDENCIAS_POR_REPORTE';

    public const TAMANO_MAXIMO_EVIDENCIA_MB = 'TAMANO_MAXIMO_EVIDENCIA_MB';

    public const JORNADA_HORA_INICIO = 'JORNADA_HORA_INICIO';

    public const JORNADA_HORA_FIN = 'JORNADA_HORA_FIN';

    public const UMBRAL_ALERTA_SLA_PORCENTAJE = 'UMBRAL_ALERTA_SLA_PORCENTAJE';

    public const TOPE_AUTORIDAD_COORDINADOR = 'TOPE_AUTORIDAD_COORDINADOR';

    public const RECURRENCIA_MINIMO_REPORTES = 'RECURRENCIA_MINIMO_REPORTES';

    public const RECURRENCIA_VENTANA_DIAS = 'RECURRENCIA_VENTANA_DIAS';

    public const LOGIN_MAXIMO_INTENTOS = 'LOGIN_MAXIMO_INTENTOS';

    public const LOGIN_VENTANA_MINUTOS = 'LOGIN_VENTANA_MINUTOS';

    public const LOGIN_BLOQUEO_MINUTOS = 'LOGIN_BLOQUEO_MINUTOS';

    public const RECUPERACION_EXPIRACION_MINUTOS = 'RECUPERACION_EXPIRACION_MINUTOS';

    /**
     * Valores por defecto: clave => [valor, tipo, grupo, descripción].
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function porDefecto(): array
    {
        return [
            self::LIMITE_WIP_TECNICO => ['5', 'entero', 'CAPACIDAD', 'Máximo de órdenes activas simultáneas por técnico (WIP limit).'],
            self::PLAZO_ACLARACION_DIAS_HABILES => ['5', 'entero', 'PLAZOS', 'Días hábiles de espera en E02 antes de cancelar por falta de respuesta (RN-010).'],
            self::VENTANA_REAPERTURA_DIAS_HABILES => ['5', 'entero', 'GARANTIA', 'Días hábiles tras el cierre (E15) en los que se permite reabrir.'],
            self::MAXIMO_REAPERTURAS => ['2', 'entero', 'GARANTIA', 'Número máximo de reaperturas permitidas por incidencia.'],
            self::PLAZO_CONFIRMACION_DIAS_HABILES => ['5', 'entero', 'PLAZOS', 'Días hábiles en E14 tras los cuales el coordinador puede cerrar administrativamente.'],
            self::VENTANA_DUPLICADOS_DIAS => ['15', 'entero', 'DUPLICADOS', 'Días hacia atrás para buscar coincidencias activas en el mismo espacio (RN-011).'],
            self::MAX_EVIDENCIAS_POR_REPORTE => ['4', 'entero', 'EVIDENCIAS', 'Máximo de archivos adjuntos por reporte.'],
            self::TAMANO_MAXIMO_EVIDENCIA_MB => ['10', 'entero', 'EVIDENCIAS', 'Tamaño máximo por archivo de evidencia en MB.'],
            self::JORNADA_HORA_INICIO => ['8', 'entero', 'SLA', 'Hora de inicio de la jornada hábil (0-23).'],
            self::JORNADA_HORA_FIN => ['18', 'entero', 'SLA', 'Hora de fin de la jornada hábil (1-24).'],
            self::UMBRAL_ALERTA_SLA_PORCENTAJE => ['75', 'entero', 'SLA', 'Porcentaje de consumo del plazo a partir del cual la incidencia está próxima a vencer.'],
            self::TOPE_AUTORIDAD_COORDINADOR => ['2000000', 'entero', 'AUTORIZACIONES', 'Costo estimado máximo (COP) que el coordinador puede autorizar por delegación.'],
            self::RECURRENCIA_MINIMO_REPORTES => ['3', 'entero', 'ANALITICA', 'Reportes de la misma categoría en un espacio para considerarlo falla recurrente.'],
            self::RECURRENCIA_VENTANA_DIAS => ['30', 'entero', 'ANALITICA', 'Ventana en días para detectar fallas recurrentes.'],
            self::LOGIN_MAXIMO_INTENTOS => ['5', 'entero', 'SEGURIDAD', 'Intentos fallidos consecutivos antes de bloquear la cuenta.'],
            self::LOGIN_VENTANA_MINUTOS => ['10', 'entero', 'SEGURIDAD', 'Ventana en minutos en la que se cuentan los intentos fallidos.'],
            self::LOGIN_BLOQUEO_MINUTOS => ['15', 'entero', 'SEGURIDAD', 'Minutos de bloqueo preventivo de la cuenta.'],
            self::RECUPERACION_EXPIRACION_MINUTOS => ['30', 'entero', 'SEGURIDAD', 'Minutos de vigencia del enlace de restablecimiento de contraseña.'],
        ];
    }
}
