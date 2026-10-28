<?php

namespace App\Services;

use App\Enums\CodigoPrioridad;
use App\Enums\EstadoIncidencia as E;
use App\Enums\EtapaEvidencia;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Incidencia;
use App\Models\User;
use App\Models\Verificacion;
use App\Support\ResultadoCierre;
use Illuminate\Support\Facades\DB;

/**
 * Verificación de calidad (HU-9.1, HU-9.2): segregación de funciones, evidencias obligatorias y reproceso.
 */
class VerificacionService
{
    public const MENSAJE_SEGREGACION = 'Por control de calidad en trabajos de riesgo, el verificador debe ser una persona distinta al técnico ejecutor (D03)';

    public function __construct(
        private MaquinaEstados $maquina,
        private Notificador $notificador,
    ) {}

    /**
     * Resultado conforme: E12 -> E14 si la categoría exige confirmación (RN-040) o E12 -> E15 (RN-041).
     *
     * @param  array<string, mixed>  $datos
     */
    public function aprobar(Incidencia $incidencia, User $usuario, array $datos): Verificacion
    {
        $this->validarVerificable($incidencia, $usuario);

        if (collect($datos['criterios'] ?? [])->contains(fn (array $criterio) => ! $criterio['cumple'])) {
            throw new ReglaNegocioException('No se puede aprobar la intervención con criterios de aceptación incumplidos; registre el rechazo.', 'RN-034');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos): Verificacion {
            $verificacion = $this->crear($incidencia, $usuario, true, $datos);
            $requiereConfirmacion = (bool) $incidencia->categoria()->value('requiere_confirmacion_cierre');

            if ($requiereConfirmacion) {
                $this->maquina->transicionar($incidencia, E::PendienteConfirmacion, $usuario, $datos['observaciones'] ?? 'Verificación conforme.', 'DICTAMEN_CALIDAD', ['verificacion_id' => $verificacion->id, 'resultado' => 'CONFORME']);
                $this->notificador->alReportante($incidencia, 'CONFIRMACION_REQUERIDA', "Confirma la solución de {$incidencia->codigo_ticket}", 'El trabajo fue verificado. Por favor confirma si el problema quedó resuelto.');
            } else {
                $incidencia->resultado_final_cierre = ResultadoCierre::VERIFICADA_SIN_CONFIRMACION;
                $incidencia->notas_cierre = $datos['observaciones'] ?? null;
                $this->maquina->transicionar($incidencia, E::Cerrada, $usuario, $datos['observaciones'] ?? 'Verificación conforme. Cierre directo.', 'DICTAMEN_CALIDAD', ['verificacion_id' => $verificacion->id, 'resultado' => 'CONFORME']);
                $this->notificador->alReportante($incidencia, 'CIERRE', "Tu solicitud {$incidencia->codigo_ticket} fue cerrada", 'La intervención superó la verificación de calidad.');
            }

            return $verificacion;
        });
    }

    /**
     * Resultado no conforme con motivo y corrección requerida (RN-035): E12 -> E13.
     *
     * @param  array<string, mixed>  $datos
     */
    public function rechazar(Incidencia $incidencia, User $usuario, array $datos): Verificacion
    {
        $this->validarVerificable($incidencia, $usuario);

        return DB::transaction(function () use ($incidencia, $usuario, $datos): Verificacion {
            $verificacion = $this->crear($incidencia, $usuario, false, $datos);
            $incidencia->numero_reprocesos++;

            $this->maquina->transicionar($incidencia, E::EnCorreccion, $usuario, $datos['motivo_rechazo'], 'DICTAMEN_CALIDAD', [
                'verificacion_id' => $verificacion->id,
                'resultado' => 'NO_CONFORME',
                'correccion_requerida' => $datos['correccion_requerida'],
                'ciclo_reproceso' => $incidencia->numero_reprocesos,
            ]);

            $mensaje = "Motivo: {$datos['motivo_rechazo']}. Corrección: {$datos['correccion_requerida']}";
            $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'VERIFICACION_RECHAZADA', "Verificación no conforme en {$incidencia->codigo_ticket}", $mensaje);
            $this->notificador->aUsuario($incidencia, $incidencia->tecnicoAsignado, 'VERIFICACION_RECHAZADA', "Verificación no conforme en {$incidencia->codigo_ticket}", $mensaje);

            return $verificacion;
        });
    }

    private function validarVerificable(Incidencia $incidencia, User $usuario): void
    {
        if (! $incidencia->estaEn(E::PendienteVerificacion)) {
            throw ReglaNegocioException::conflicto('Solo se pueden verificar incidencias Pendientes de verificación (E12).', 'RN-034');
        }

        $actividad = $incidencia->actividades()->whereNotNull('fecha_fin')->latest('id')->first();

        if ($actividad === null || $incidencia->evidencias()->where('etapa', EtapaEvidencia::Ejecucion->value)->doesntExist()) {
            throw new ReglaNegocioException('El expediente no cuenta con las evidencias obligatorias del trabajo realizado (RN-033).', 'RN-033');
        }

        $esRiesgoAlto = $incidencia->es_riesgo_inmediato
            || $incidencia->prioridad()->value('codigo') === CodigoPrioridad::Critica->value
            || $incidencia->categoria()->value('nivel_riesgo_base') === 'ALTO';

        if ($esRiesgoAlto && (int) $actividad->tecnico_ejecutor_id === (int) $usuario->id) {
            throw new ReglaNegocioException(self::MENSAJE_SEGREGACION, 'D03', 403);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crear(Incidencia $incidencia, User $usuario, bool $conforme, array $datos): Verificacion
    {
        return Verificacion::create([
            'incidencia_id' => $incidencia->id,
            'actividad_mantenimiento_id' => $incidencia->actividades()->latest('id')->value('id'),
            'verificador_id' => $usuario->id,
            'resultado_conforme' => $conforme,
            'criterios_evaluados' => $datos['criterios'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'motivo_rechazo' => $conforme ? null : $datos['motivo_rechazo'],
            'correccion_requerida' => $conforme ? null : $datos['correccion_requerida'],
            'created_at' => now(),
        ]);
    }
}
