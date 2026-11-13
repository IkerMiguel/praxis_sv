<?php

namespace App\Services;

use App\Enums\CodigoPrioridad;
use App\Enums\EstadoIncidencia as E;
use App\Enums\EtapaEvidencia;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\HistorialCambioPrioridad;
use App\Models\Incidencia;
use App\Models\SolicitudAclaracion;
use App\Models\User;
use App\Support\Parametro;
use App\Support\PoliticaReapertura;
use App\Support\ResultadoCierre;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Acciones del reportante sobre sus propias solicitudes (HU-4.3, HU-4.4, HU-4.5, HU-9.3).
 */
class ReportanteService
{
    public function __construct(
        private MaquinaEstados $maquina,
        private EvidenciaService $evidencias,
        private Notificador $notificador,
        private AuditoriaService $auditoria,
        private ParametrosService $parametros,
        private CalculadoraSla $sla,
        private CatalogoService $catalogo,
    ) {}

    /**
     * Respuesta a la aclaración pendiente: E02 -> E03 (RN-009).
     *
     * @param  list<int>  $idsEvidencias
     */
    public function responderAclaracion(Incidencia $incidencia, User $usuario, string $respuesta, array $idsEvidencias): SolicitudAclaracion
    {
        $this->exigirPropietario($incidencia, $usuario);

        if (! $incidencia->estaEn(E::PendienteInformacion)) {
            throw ReglaNegocioException::conflicto('Solo es posible aportar aclaraciones cuando la solicitud está en Pendiente de información (RN-008).', 'RN-008');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $respuesta, $idsEvidencias): SolicitudAclaracion {
            $aclaracion = $incidencia->solicitudesAclaracion()->where('estado', SolicitudAclaracion::PENDIENTE)->latest('id')->lockForUpdate()->firstOrFail();

            $aclaracion->forceFill([
                'respuesta_reportante' => $respuesta,
                'fecha_respuesta' => now(),
                'respondida_por_id' => $usuario->id,
                'estado' => SolicitudAclaracion::RESPONDIDA,
            ])->save();

            $evidencias = $this->evidencias->vincularPendientes($idsEvidencias, $usuario, $incidencia, EtapaEvidencia::Aclaracion, 'solicitud_aclaracion', $aclaracion->id);

            $this->maquina->transicionar(
                $incidencia,
                E::EnRevision,
                $usuario,
                motivo: $respuesta,
                tipoEvento: 'ACLARACION_RESPONDIDA',
                datos: ['solicitud_aclaracion_id' => $aclaracion->id],
                evidencias: $this->evidencias->paraAuditoria($evidencias),
                actuarComo: Rol::Reportante->value,
            );

            $this->notificador->aUsuario(
                $incidencia,
                $aclaracion->gestor,
                'ACLARACION_RECIBIDA',
                "Aclaración recibida en {$incidencia->codigo_ticket}",
                'El reportante respondió la solicitud de información. La incidencia volvió a En revisión.',
            );

            return $aclaracion;
        });
    }

    /**
     * Cancelación por el reportante antes de la asignación técnica (E01-E04 -> E17).
     */
    public function cancelar(Incidencia $incidencia, User $usuario, string $motivo): void
    {
        $this->exigirPropietario($incidencia, $usuario);

        if (! $incidencia->estaEn(E::Registrada, E::PendienteInformacion, E::EnRevision, E::EnClasificacion)) {
            throw ReglaNegocioException::conflicto(
                $incidencia->estado_codigo->esTerminal()
                    ? 'La solicitud ya se encuentra finalizada.'
                    : 'La solicitud ya cuenta con personal técnico asignado. Para novedades comuníquese con Coordinación de Mantenimiento',
                'HU-4.4',
            );
        }

        DB::transaction(function () use ($incidencia, $usuario, $motivo): void {
            $incidencia->solicitudesAclaracion()->where('estado', SolicitudAclaracion::PENDIENTE)->update(['estado' => SolicitudAclaracion::VENCIDA]);
            $incidencia->motivo_cancelacion = $motivo;
            $incidencia->usuario_cancelacion_id = $usuario->id;
            $incidencia->resultado_final_cierre = ResultadoCierre::CANCELADA_POR_REPORTANTE;

            $this->maquina->transicionar($incidencia, E::Cancelada, $usuario, $motivo, 'CANCELACION_REPORTANTE', actuarComo: Rol::Reportante->value);
        });
    }

    /**
     * Evalúa si la incidencia puede reabrirse (ventana de garantía y cupo de reaperturas, HU-4.5).
     *
     * @return array{permitido: bool, motivo: string|null, fecha_limite: string|null, reaperturas_restantes: int}
     */
    public function evaluarReapertura(Incidencia $incidencia): array
    {
        $fechaCierre = $incidencia->fecha_cierre === null ? null : CarbonImmutable::parse($incidencia->fecha_cierre);

        return PoliticaReapertura::evaluar(
            $incidencia->estado_codigo,
            $fechaCierre,
            $incidencia->resultado_final_cierre,
            (int) $incidencia->numero_reaperturas,
            $this->parametros->entero(Parametro::MAXIMO_REAPERTURAS),
            $fechaCierre === null ? null : $this->sla->sumarDiasHabiles($fechaCierre, $this->parametros->entero(Parametro::VENTANA_REAPERTURA_DIAS_HABILES)),
            now(),
        );
    }

    /**
     * Reapertura controlada: E15 -> E13 (si hubo intervención) o E05, con prioridad Alta y alerta al coordinador.
     */
    public function reabrir(Incidencia $incidencia, User $usuario, string $motivo): void
    {
        $this->exigirPropietario($incidencia, $usuario);
        $evaluacion = $this->evaluarReapertura($incidencia);

        if (! $evaluacion['permitido']) {
            throw new ReglaNegocioException($evaluacion['motivo'], 'HU-4.5', 422, [
                'alternativa' => 'CREAR_NUEVO_REPORTE',
                'incidencia_origen' => $incidencia->codigo_ticket,
            ]);
        }

        DB::transaction(function () use ($incidencia, $usuario, $motivo): void {
            $destino = $incidencia->actividades()->exists() && $incidencia->tecnico_asignado_id !== null ? E::EnCorreccion : E::PendienteAsignacion;
            $alta = $this->catalogo->prioridadPorCodigo(CodigoPrioridad::Alta->value);

            HistorialCambioPrioridad::create([
                'incidencia_id' => $incidencia->id,
                'prioridad_anterior_id' => $incidencia->prioridad_id,
                'prioridad_nueva_id' => $alta->id,
                'origen' => 'REAPERTURA',
                'usuario_id' => $usuario->id,
                'justificacion' => $motivo,
                'created_at' => now(),
            ]);

            $incidencia->prioridad_id = $alta->id;
            $incidencia->numero_reaperturas++;
            $incidencia->fecha_ultima_reapertura = now();
            $incidencia->fecha_limite_resolucion = $this->sla->sumarHorasHabiles(now(), $alta->horas_resolucion);

            if ($destino === E::PendienteAsignacion) {
                $incidencia->tecnico_asignado_id = null;
                $incidencia->asignaciones()->where('es_vigente', true)->update(['es_vigente' => false, 'fecha_fin_vigencia' => now()]);
            }

            $this->maquina->transicionar(
                $incidencia,
                $destino,
                $usuario,
                $motivo,
                'REAPERTURA',
                ['numero_reapertura' => $incidencia->numero_reaperturas, 'prioridad' => CodigoPrioridad::Alta->value],
                actuarComo: Rol::Reportante->value,
            );

            $this->notificador->aRoles(
                $incidencia,
                [Rol::Coordinador],
                'REAPERTURA',
                "Incidencia {$incidencia->codigo_ticket} reabierta",
                "El reportante reabrió la solicitud: {$motivo}",
            );
        });
    }

    /**
     * Confirmación de la solución por el reportante (E14 -> E15, RN-040).
     */
    public function confirmar(Incidencia $incidencia, User $usuario, int $calificacion, ?string $comentario): void
    {
        $this->exigirPropietarioOAdministrador($incidencia, $usuario);

        if (! $incidencia->estaEn(E::PendienteConfirmacion)) {
            throw ReglaNegocioException::conflicto('La solicitud no está pendiente de confirmación.', 'RN-040');
        }

        DB::transaction(function () use ($incidencia, $usuario, $calificacion, $comentario): void {
            $incidencia->calificacion_servicio = $calificacion;
            $incidencia->comentario_conformidad = $comentario;
            $incidencia->fecha_confirmacion = now();
            $incidencia->resultado_final_cierre = ResultadoCierre::SOLUCION_CONFIRMADA;
            $incidencia->notas_cierre = $comentario;

            $this->maquina->transicionar(
                $incidencia,
                E::Cerrada,
                $usuario,
                $comentario,
                'CIERRE',
                ['calificacion' => $calificacion, 'resultado' => ResultadoCierre::SOLUCION_CONFIRMADA],
                actuarComo: $incidencia->esCreadaPor($usuario) ? Rol::Reportante->value : null,
            );

            $this->notificador->alReportante($incidencia, 'CIERRE', "Tu solicitud {$incidencia->codigo_ticket} fue cerrada", 'Gracias por confirmar la solución. El expediente quedó cerrado.');
        });
    }

    /**
     * Rechazo fundamentado de la solución (E14 -> E13).
     */
    public function rechazarConformidad(Incidencia $incidencia, User $usuario, string $motivo): void
    {
        $this->exigirPropietarioOAdministrador($incidencia, $usuario);

        if (! $incidencia->estaEn(E::PendienteConfirmacion)) {
            throw ReglaNegocioException::conflicto('La solicitud no está pendiente de confirmación.', 'RN-040');
        }

        DB::transaction(function () use ($incidencia, $usuario, $motivo): void {
            $incidencia->numero_reprocesos++;

            $this->maquina->transicionar(
                $incidencia,
                E::EnCorreccion,
                $usuario,
                $motivo,
                'CONFORMIDAD_RECHAZADA',
                actuarComo: $incidencia->esCreadaPor($usuario) ? Rol::Reportante->value : null,
            );

            $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'CONFORMIDAD_RECHAZADA', "El reportante rechazó la solución de {$incidencia->codigo_ticket}", $motivo);
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function exigirPropietario(Incidencia $incidencia, User $usuario): void
    {
        if (! $incidencia->esCreadaPor($usuario)) {
            throw new AuthorizationException('Solo el reportante que creó la solicitud puede realizar esta acción.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function exigirPropietarioOAdministrador(Incidencia $incidencia, User $usuario): void
    {
        if (! $incidencia->esCreadaPor($usuario) && ! $usuario->tieneRol(Rol::Administrador)) {
            throw new AuthorizationException('Solo el reportante que creó la solicitud puede confirmar o rechazar la solución.');
        }
    }
}
