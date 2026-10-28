<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\EstadoIncidencia as E;
use App\Enums\EstadoSolicitudRecursos as S;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Diagnostico;
use App\Models\Incidencia;
use App\Models\SeguimientoRecursos;
use App\Models\SolicitudRecursos;
use App\Models\User;
use App\Support\ContextoRol;
use App\Support\Parametro;
use App\Support\ResultadoCierre;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Solicitud de recursos (HU-8.1, RN-026..RN-029), dictamen de autorización (HU-8.2, RN-028)
 * y habilitación por disponibilidad (HU-8.3, RN-030, RN-031).
 */
class RecursosService
{
    public function __construct(
        private MaquinaEstados $maquina,
        private AuditoriaService $auditoria,
        private Notificador $notificador,
        private ParametrosService $parametros,
        private PlanificacionService $planificacion,
        private ContextoRol $contextoRol,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function solicitar(Incidencia $incidencia, User $usuario, array $datos): SolicitudRecursos
    {
        if (! $incidencia->estaEn(E::EnDiagnostico)) {
            throw ReglaNegocioException::conflicto('Las solicitudes de recursos se registran con la incidencia En diagnóstico (E07).', 'D07');
        }

        $diagnostico = $incidencia->diagnosticos()->latest('id')->first();

        if ($diagnostico === null || $diagnostico->estado !== Diagnostico::REGISTRADO || $diagnostico->incidencia_confirmada === false) {
            throw ReglaNegocioException::conflicto('Debe existir un diagnóstico confirmado para solicitar recursos.', 'RN-026');
        }

        $emergencia = (bool) ($datos['es_emergencia'] ?? false);

        if ($emergencia && ! $incidencia->protocolo_emergencia_activo) {
            throw new ReglaNegocioException('La modalidad de emergencia solo aplica a incidencias con protocolo de riesgo inmediato activo (RN-029).', 'RN-029');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos, $diagnostico, $emergencia): SolicitudRecursos {
            $requiereAutorizacion = (bool) $datos['requiere_autorizacion'];

            $solicitud = SolicitudRecursos::create([
                'incidencia_id' => $incidencia->id,
                'diagnostico_id' => $diagnostico->id,
                'solicitante_id' => $usuario->id,
                'decision_requerida' => $datos['decision_requerida'],
                'requiere_autorizacion' => $requiereAutorizacion,
                'es_emergencia' => $emergencia,
                'estado_solicitud' => $requiereAutorizacion ? S::PendienteAutorizacion : S::PendienteRecursos,
            ]);

            $solicitud->detalles()->createMany(collect($datos['items'])->map(fn (array $item) => [
                'recurso_nombre' => $item['recurso_nombre'],
                'cantidad' => $item['cantidad'],
                'unidad' => $item['unidad'],
                'costo_estimado' => $item['costo_estimado'] ?? 0,
                'disponible' => false,
            ])->all());

            $diagnostico->forceFill(['estado' => Diagnostico::APROBADO, 'revisado_por_id' => $usuario->id, 'fecha_revision' => now()])->save();
            $datosEvento = ['solicitud_recursos_id' => $solicitud->id, 'costo_total_estimado' => $solicitud->load('detalles')->costoTotalEstimado(), 'es_emergencia' => $emergencia];

            if ($emergencia) {
                $this->auditoria->registrar(
                    tipoEvento: 'RECURSOS_EMERGENCIA',
                    categoria: CategoriaEvento::Forense,
                    incidencia: $incidencia,
                    usuario: $usuario,
                    motivo: 'Emergencia autorizada: se permite avanzar a ejecución con regularización documental posterior (RN-029).',
                    datos: $datosEvento,
                );
                $this->notificador->aRoles($incidencia, [Rol::Autorizador], 'REGULARIZACION_PENDIENTE', "Regularización pendiente en {$incidencia->codigo_ticket}", $datos['decision_requerida']);

                return $solicitud;
            }

            if ($requiereAutorizacion) {
                $this->maquina->transicionar($incidencia, E::PendienteAutorizacion, $usuario, $datos['decision_requerida'], 'SOLICITUD_AUTORIZACION', $datosEvento);
                $this->notificador->aRoles($incidencia, [Rol::Autorizador], 'AUTORIZACION_PENDIENTE', "Autorización requerida en {$incidencia->codigo_ticket}", $datos['decision_requerida']);
            } else {
                $this->maquina->transicionar($incidencia, E::PendienteRecursos, $usuario, $datos['decision_requerida'], 'SOLICITUD_RECURSOS', $datosEvento);
            }

            return $solicitud;
        });
    }

    /**
     * Dictamen (RN-028): aprobada -> E10 (recursos disponibles) o E09; rechazada -> E07, E18 o E17.
     *
     * @param  array<string, mixed>  $datos
     */
    public function dictaminar(SolicitudRecursos $solicitud, User $usuario, array $datos): void
    {
        $incidencia = $solicitud->incidencia;
        $solicitud->loadMissing('detalles');

        if ($solicitud->estado_solicitud !== S::PendienteAutorizacion) {
            throw ReglaNegocioException::conflicto('La solicitud no está pendiente de autorización.', 'RN-028');
        }

        if ($this->contextoRol->es(Rol::Coordinador)) {
            $tope = $this->parametros->entero(Parametro::TOPE_AUTORIDAD_COORDINADOR);

            if ($solicitud->costoTotalEstimado() > $tope) {
                throw new AuthorizationException('El costo estimado supera el tope de autoridad delegada del coordinador; requiere dictamen del Responsable de autorizaciones (R05).');
            }
        }

        if (! $solicitud->es_emergencia && ! $incidencia->estaEn(E::PendienteAutorizacion)) {
            throw ReglaNegocioException::conflicto('La incidencia no está Pendiente de autorización (E08).', 'D07');
        }

        DB::transaction(function () use ($solicitud, $incidencia, $usuario, $datos): void {
            $aprobada = $datos['resultado'] === 'APROBADA';
            $disponibles = $aprobada && (bool) ($datos['recursos_disponibles'] ?? false);

            $solicitud->forceFill([
                'autorizador_id' => $usuario->id,
                'resultado_dictamen' => $datos['resultado'],
                'justificacion_dictamen' => $datos['justificacion'] ?? null,
                'recursos_disponibles' => $aprobada ? $disponibles : null,
                'fecha_dictamen' => now(),
                'estado_solicitud' => match (true) {
                    ! $aprobada => S::Rechazada,
                    $disponibles => S::Disponible,
                    default => S::PendienteRecursos,
                },
                'fecha_disponibilidad' => $disponibles ? now() : null,
            ])->save();

            if ($disponibles) {
                $solicitud->detalles()->update(['disponible' => true]);
            }

            $datosEvento = [
                'solicitud_recursos_id' => $solicitud->id,
                'resultado' => $datos['resultado'],
                'recursos_disponibles' => $disponibles,
                'costo_total_estimado' => $solicitud->costoTotalEstimado(),
            ];

            if ($solicitud->es_emergencia) {
                $this->auditoria->registrar(tipoEvento: 'AUTORIZACION', categoria: CategoriaEvento::Forense, incidencia: $incidencia, usuario: $usuario, motivo: $datos['justificacion'] ?? null, datos: $datosEvento + ['regularizacion' => true]);

                return;
            }

            if ($aprobada) {
                if ($disponibles && isset($datos['fecha_programada'])) {
                    $plan = $this->planificacion->crearPlan($incidencia, $usuario, $datos, $solicitud->diagnostico_id);
                    $datosEvento += $this->planificacion->datosPlan($plan);
                }

                $this->maquina->transicionar($incidencia, $disponibles ? E::Programada : E::PendienteRecursos, $usuario, $datos['justificacion'] ?? 'Solicitud aprobada.', 'AUTORIZACION', $datosEvento, publico: false);
                $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'AUTORIZACION_APROBADA', "Solicitud aprobada en {$incidencia->codigo_ticket}", $datos['justificacion'] ?? 'Aprobada.');

                return;
            }

            match ($datos['accion_rechazo']) {
                'DEVOLVER_DIAGNOSTICO' => $this->maquina->transicionar($incidencia, E::EnDiagnostico, $usuario, $datos['justificacion'], 'AUTORIZACION', $datosEvento, publico: false),
                'SUSPENDER' => $this->suspenderPorRechazo($incidencia, $usuario, $datos, $datosEvento),
                'CANCELAR' => $this->cancelarPorRechazo($incidencia, $usuario, $datos, $datosEvento),
            };

            if ($datos['accion_rechazo'] === 'DEVOLVER_DIAGNOSTICO') {
                $incidencia->diagnosticos()->latest('id')->first()?->forceFill(['estado' => Diagnostico::REGISTRADO])->save();
            }

            $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'AUTORIZACION_RECHAZADA', "Solicitud rechazada en {$incidencia->codigo_ticket}", $datos['justificacion']);
        });
    }

    /**
     * Confirmación de recepción completa de recursos (E09 -> E10).
     *
     * @param  array<string, mixed>  $datos
     */
    public function confirmarRecursos(Incidencia $incidencia, User $usuario, array $datos): void
    {
        if (! $incidencia->estaEn(E::PendienteRecursos)) {
            throw ReglaNegocioException::conflicto('La incidencia no está Pendiente de recursos (E09).', 'D07');
        }

        DB::transaction(function () use ($incidencia, $usuario, $datos): void {
            $solicitudes = $incidencia->solicitudesRecursos()->where('estado_solicitud', S::PendienteRecursos->value)->with('detalles')->lockForUpdate()->get();

            foreach ($solicitudes as $solicitud) {
                if (! empty($datos['detalles_disponibles'])) {
                    $solicitud->detalles()->whereIn('id', $datos['detalles_disponibles'])->update(['disponible' => true]);
                } elseif ($datos['todos_disponibles'] ?? false) {
                    $solicitud->detalles()->update(['disponible' => true]);
                }
            }

            $faltantes = $solicitudes->flatMap(fn (SolicitudRecursos $solicitud) => $solicitud->detalles()->where('disponible', false)->pluck('recurso_nombre'));

            if ($faltantes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'detalles_disponibles' => 'Todos los recursos requeridos deben estar disponibles antes de programar. Faltan: '.$faltantes->implode(', '),
                ]);
            }

            $solicitudes->each(fn (SolicitudRecursos $solicitud) => $solicitud->forceFill(['estado_solicitud' => S::Disponible, 'fecha_disponibilidad' => now()])->save());
            $plan = $this->planificacion->crearPlan($incidencia, $usuario, $datos, $incidencia->diagnosticos()->latest('id')->value('id'));

            $this->maquina->transicionar($incidencia, E::Programada, $usuario, $datos['observaciones'] ?? 'Recursos disponibles en almacén.', 'RECURSOS_DISPONIBLES', $this->planificacion->datosPlan($plan));
            $this->planificacion->notificarProgramacion($incidencia, $plan);
        });
    }

    /**
     * Nota de seguimiento mientras se esperan recursos (RN-030); no cambia el estado.
     */
    public function registrarSeguimiento(SolicitudRecursos $solicitud, User $usuario, string $comentario): SeguimientoRecursos
    {
        if (! in_array($solicitud->estado_solicitud, [S::PendienteAutorizacion, S::PendienteRecursos], true)) {
            throw ReglaNegocioException::conflicto('Solo se registran seguimientos de solicitudes pendientes.', 'RN-030');
        }

        return DB::transaction(function () use ($solicitud, $usuario, $comentario): SeguimientoRecursos {
            $seguimiento = SeguimientoRecursos::create([
                'solicitud_recurso_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'comentario_seguimiento' => $comentario,
                'created_at' => now(),
            ]);

            $this->auditoria->registrar(
                tipoEvento: 'SEGUIMIENTO_RECURSOS',
                categoria: CategoriaEvento::Actividad,
                incidencia: $solicitud->incidencia,
                usuario: $usuario,
                motivo: $comentario,
                datos: ['solicitud_recursos_id' => $solicitud->id],
            );

            return $seguimiento;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $datosEvento
     */
    private function suspenderPorRechazo(Incidencia $incidencia, User $usuario, array $datos, array $datosEvento): void
    {
        $suspension = $this->planificacion->registrarSuspension($incidencia, $usuario, 'RECHAZO_AUTORIZACION', $datos['justificacion'], $datos['condiciones_reanudacion']);
        $this->maquina->transicionar($incidencia, E::Suspendida, $usuario, $datos['justificacion'], 'AUTORIZACION', $datosEvento + ['suspension_id' => $suspension->id]);
        $this->planificacion->comunicarSuspension($incidencia, $suspension);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $datosEvento
     */
    private function cancelarPorRechazo(Incidencia $incidencia, User $usuario, array $datos, array $datosEvento): void
    {
        $incidencia->motivo_cancelacion = $datos['justificacion'];
        $incidencia->usuario_cancelacion_id = $usuario->id;
        $incidencia->resultado_final_cierre = ResultadoCierre::CANCELADA_ADMINISTRATIVAMENTE;
        $incidencia->notas_cierre = $datos['justificacion'];
        $this->maquina->transicionar($incidencia, E::Cancelada, $usuario, $datos['justificacion'], 'AUTORIZACION', $datosEvento);
        $this->notificador->alReportante($incidencia, 'CANCELACION', "Tu solicitud {$incidencia->codigo_ticket} fue cancelada", $datos['justificacion']);
    }
}
