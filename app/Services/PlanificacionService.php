<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\CodigoPrioridad;
use App\Enums\EstadoIncidencia as E;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\AsignacionIncidencia;
use App\Models\CategoriaIncidencia;
use App\Models\Diagnostico;
use App\Models\Incidencia;
use App\Models\PlanIntervencion;
use App\Models\ReprogramacionPlan;
use App\Models\Suspension;
use App\Models\User;
use App\Support\ContextoRol;
use App\Support\Parametro;
use App\Support\ResultadoCierre;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coordinación de mantenimiento: asignación con límite WIP (HU-6.1, HU-6.2), evaluación del diagnóstico
 * y programación (HU-6.3), suspensión y reanudación (HU-7.4), reproceso (HU-9.2) y cierre administrativo (HU-9.3).
 */
class PlanificacionService
{
    public function __construct(
        private MaquinaEstados $maquina,
        private AuditoriaService $auditoria,
        private Notificador $notificador,
        private ParametrosService $parametros,
        private CalculadoraSla $sla,
        private ContextoRol $contextoRol,
    ) {}

    /**
     * Técnicos R04 activos de la especialidad de la categoría con su carga actual (E06, E07, E11, E13).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tecnicosDisponibles(?int $categoriaId): Collection
    {
        $limite = $this->parametros->entero(Parametro::LIMITE_WIP_TECNICO);
        $especialidadId = $categoriaId === null ? null : CategoriaIncidencia::query()->whereKey($categoriaId)->value('especialidad_id');
        $estadosCarga = E::valores(E::cargaTecnico());

        return User::query()
            ->activos()
            ->conRol(Rol::Tecnico)
            ->when($especialidadId !== null, fn ($query) => $query->whereHas('especialidades', fn ($especialidades) => $especialidades->whereKey($especialidadId)))
            ->with('especialidades')
            ->withCount(['incidenciasAsignadas as carga_actual' => fn ($query) => $query->whereIn('estado_codigo', $estadosCarga)])
            ->orderBy('carga_actual')
            ->orderBy('name')
            ->get()
            ->map(fn (User $tecnico) => [
                'id' => $tecnico->id,
                'nombre' => $tecnico->name,
                'email' => $tecnico->email,
                'especialidades' => $tecnico->especialidades->map(fn ($especialidad) => $especialidad->only(['id', 'codigo', 'nombre']))->values(),
                'carga_actual' => (int) $tecnico->carga_actual,
                'limite_wip' => $limite,
                'disponible' => $tecnico->carga_actual < $limite,
            ]);
    }

    /**
     * Asignación (E05 -> E06) o reasignación con motivo (RN-025), respetando el WIP con excepción por prioridad crítica.
     *
     * @param  array<string, mixed>  $datos
     */
    public function asignar(Incidencia $incidencia, User $usuario, array $datos): AsignacionIncidencia
    {
        $esReasignacion = $incidencia->tecnico_asignado_id !== null;

        if (! $esReasignacion && ! $incidencia->estaEn(E::PendienteAsignacion)) {
            throw ReglaNegocioException::conflicto('Solo se pueden asignar incidencias en Pendiente de asignación (E05).', 'D07');
        }

        if ($esReasignacion && ($incidencia->estado_codigo->esTerminal() || $incidencia->estaEn(E::PendienteVerificacion, E::PendienteConfirmacion))) {
            throw ReglaNegocioException::conflicto('La incidencia no admite reasignación en su estado actual.', 'RN-025');
        }

        if ($esReasignacion && blank($datos['motivo_reasignacion'] ?? null)) {
            throw ValidationException::withMessages(['motivo_reasignacion' => 'La reasignación exige registrar el motivo del relevo (RN-025).']);
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos, $esReasignacion): AsignacionIncidencia {
            $tecnico = User::query()->whereKey($datos['tecnico_id'])->lockForUpdate()->first();
            $this->validarTecnico($incidencia, $tecnico);

            if ($esReasignacion && (int) $tecnico->id === (int) $incidencia->tecnico_asignado_id) {
                throw ValidationException::withMessages(['tecnico_id' => 'El técnico seleccionado ya es el responsable de la incidencia.']);
            }

            $limite = $this->parametros->entero(Parametro::LIMITE_WIP_TECNICO);
            $carga = $tecnico->incidenciasAsignadas()->enEstados(E::cargaTecnico())->count();
            $excepcion = false;

            if ($carga >= $limite) {
                $esCritica = $incidencia->es_riesgo_inmediato || $incidencia->prioridad?->codigo === CodigoPrioridad::Critica->value;

                if (! $esCritica) {
                    throw new ReglaNegocioException(
                        "El técnico ha alcanzado el límite de {$limite} órdenes activas. Seleccione otro especialista disponible o espere liberación de carga",
                        'HU-6.2',
                        422,
                        ['carga_actual' => $carga, 'limite_wip' => $limite],
                    );
                }

                $excepcion = true;
            }

            $anteriorId = $incidencia->tecnico_asignado_id;
            $incidencia->asignaciones()->where('es_vigente', true)->update(['es_vigente' => false, 'fecha_fin_vigencia' => now()]);

            $asignacion = AsignacionIncidencia::create([
                'incidencia_id' => $incidencia->id,
                'tecnico_asignado_id' => $tecnico->id,
                'asignado_por_id' => $usuario->id,
                'tecnico_anterior_id' => $anteriorId,
                'es_vigente' => true,
                'excepcion_wip' => $excepcion,
                'motivo_reasignacion' => $esReasignacion ? $datos['motivo_reasignacion'] : null,
                'instrucciones' => $datos['instrucciones'] ?? null,
                'fecha_visita_tentativa' => $datos['fecha_visita_tentativa'] ?? null,
                'created_at' => now(),
            ]);

            $incidencia->tecnico_asignado_id = $tecnico->id;
            $incidencia->setRelation('tecnicoAsignado', $tecnico);
            $datosEvento = [
                'asignacion_id' => $asignacion->id,
                'tecnico_id' => $tecnico->id,
                'tecnico' => $tecnico->name,
                'tecnico_anterior_id' => $anteriorId,
                'excepcion_wip' => $excepcion,
                'carga_previa' => $carga,
                'fecha_visita_tentativa' => $asignacion->fecha_visita_tentativa?->toIso8601String(),
            ];

            if ($esReasignacion) {
                $incidencia->save();
                $this->auditoria->registrar(
                    tipoEvento: 'REASIGNACION',
                    categoria: CategoriaEvento::Forense,
                    incidencia: $incidencia,
                    usuario: $usuario,
                    motivo: $datos['motivo_reasignacion'],
                    datos: $datosEvento,
                );
                $anterior = User::query()->find($anteriorId);
                $this->notificador->aUsuario($incidencia, $anterior, 'REASIGNACION', "Fue relevado de {$incidencia->codigo_ticket}", $datos['motivo_reasignacion']);
            } else {
                $this->maquina->transicionar($incidencia, E::PendienteInspeccion, $usuario, $datos['instrucciones'] ?? 'Técnico asignado.', 'ASIGNACION', $datosEvento);
            }

            if ($excepcion) {
                $this->auditoria->registrar(
                    tipoEvento: 'EXCEPCION_WIP',
                    categoria: CategoriaEvento::Forense,
                    incidencia: $incidencia,
                    usuario: $usuario,
                    motivo: 'Excepción por prioridad crítica: asignación por encima del límite WIP.',
                    datos: ['tecnico_id' => $tecnico->id, 'carga_actual' => $carga, 'limite_wip' => $limite],
                );
            }

            $this->notificador->aUsuario($incidencia, $tecnico, 'ASIGNACION', "Nueva asignación: {$incidencia->codigo_ticket}", $datos['instrucciones'] ?? $incidencia->resumen);

            return $asignacion;
        });
    }

    /**
     * Aprobación del diagnóstico y programación (E07 -> E10). En E10 sin plan vigente registra el plan.
     *
     * @param  array<string, mixed>  $datos
     */
    public function programar(Incidencia $incidencia, User $usuario, array $datos): PlanIntervencion
    {
        $this->maquina->validarAutorizacionPrevia($incidencia, E::Programada);

        if (! $incidencia->estaEn(E::EnDiagnostico, E::Programada)) {
            throw ReglaNegocioException::conflicto('Solo se pueden programar incidencias con diagnóstico registrado (E07).', 'D07');
        }

        if ($incidencia->estaEn(E::Programada) && $incidencia->planVigente()->exists()) {
            throw ReglaNegocioException::conflicto('La intervención ya está programada; use la reprogramación con motivo.', 'HU-6.3');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos): PlanIntervencion {
            $diagnostico = null;

            if ($incidencia->estaEn(E::EnDiagnostico)) {
                $diagnostico = $this->diagnosticoRegistrado($incidencia, confirmado: true, admiteAprobado: true);
                $diagnostico->forceFill(['estado' => Diagnostico::APROBADO, 'revisado_por_id' => $usuario->id, 'fecha_revision' => now()])->save();
            }

            $plan = $this->crearPlan($incidencia, $usuario, $datos, $diagnostico?->id);

            if ($incidencia->estaEn(E::EnDiagnostico)) {
                $this->maquina->transicionar($incidencia, E::Programada, $usuario, $datos['observaciones'] ?? 'Intervención aprobada y programada.', 'PROGRAMACION', $this->datosPlan($plan));
            } else {
                $this->auditoria->registrar(tipoEvento: 'PROGRAMACION', categoria: CategoriaEvento::Actividad, incidencia: $incidencia, usuario: $usuario, datos: $this->datosPlan($plan));
            }

            $this->notificarProgramacion($incidencia, $plan);

            return $plan;
        });
    }

    /**
     * Reprogramación con motivo auditable conservando la fecha original (HU-6.3 escenario 4).
     *
     * @param  array<string, mixed>  $datos
     */
    public function reprogramar(Incidencia $incidencia, User $usuario, array $datos): PlanIntervencion
    {
        $plan = $incidencia->planVigente()->first();

        if (! $incidencia->estaEn(E::Programada) || $plan === null) {
            throw ReglaNegocioException::conflicto('Solo se pueden reprogramar intervenciones programadas (E10) con plan vigente.', 'HU-6.3');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos, $plan): PlanIntervencion {
            ReprogramacionPlan::create([
                'plan_intervencion_id' => $plan->id,
                'fecha_anterior' => $plan->fecha_programada,
                'hora_inicio_anterior' => $plan->hora_inicio,
                'hora_fin_anterior' => $plan->hora_fin,
                'fecha_nueva' => $datos['fecha_programada'],
                'hora_inicio_nueva' => $datos['hora_inicio'],
                'hora_fin_nueva' => $datos['hora_fin'],
                'motivo' => $datos['motivo'],
                'usuario_id' => $usuario->id,
                'created_at' => now(),
            ]);

            $anterior = $this->datosPlan($plan);
            $plan->forceFill([
                'fecha_programada' => $datos['fecha_programada'],
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin' => $datos['hora_fin'],
            ])->save();

            $this->auditoria->registrar(
                tipoEvento: 'REPROGRAMACION',
                categoria: CategoriaEvento::Forense,
                incidencia: $incidencia,
                usuario: $usuario,
                motivo: $datos['motivo'],
                datos: ['anterior' => $anterior, 'nueva' => $this->datosPlan($plan)],
            );

            $this->notificarProgramacion($incidencia, $plan, reprogramada: true);

            return $plan;
        });
    }

    /**
     * Devolución por diagnóstico insuficiente (E07 -> E06) con observaciones obligatorias.
     */
    public function devolverDiagnostico(Incidencia $incidencia, User $usuario, string $observaciones): void
    {
        if (! $incidencia->estaEn(E::EnDiagnostico)) {
            throw ReglaNegocioException::conflicto('Solo se pueden devolver diagnósticos de incidencias en En diagnóstico (E07).', 'D07');
        }

        DB::transaction(function () use ($incidencia, $usuario, $observaciones): void {
            $diagnostico = $this->diagnosticoRegistrado($incidencia);
            $diagnostico->forceFill([
                'estado' => Diagnostico::DEVUELTO,
                'observaciones_devolucion' => $observaciones,
                'revisado_por_id' => $usuario->id,
                'fecha_revision' => now(),
            ])->save();

            $this->maquina->transicionar($incidencia, E::PendienteInspeccion, $usuario, $observaciones, 'DEVOLUCION_DIAGNOSTICO', ['diagnostico_id' => $diagnostico->id], publico: false);
            $this->notificador->aUsuario($incidencia, $incidencia->tecnicoAsignado, 'DIAGNOSTICO_DEVUELTO', "Diagnóstico devuelto en {$incidencia->codigo_ticket}", $observaciones);
        });
    }

    /**
     * Cierre justificado por incidencia no confirmada en inspección (RN-024): E07 -> E15.
     */
    public function cerrarNoConfirmada(Incidencia $incidencia, User $usuario, ?string $observaciones): void
    {
        if (! $incidencia->estaEn(E::EnDiagnostico)) {
            throw ReglaNegocioException::conflicto('La incidencia debe estar En diagnóstico (E07).', 'D07');
        }

        DB::transaction(function () use ($incidencia, $usuario, $observaciones): void {
            $diagnostico = $this->diagnosticoRegistrado($incidencia, confirmado: false);
            $diagnostico->forceFill(['estado' => Diagnostico::APROBADO, 'revisado_por_id' => $usuario->id, 'fecha_revision' => now()])->save();

            $incidencia->resultado_final_cierre = ResultadoCierre::NO_CONFIRMADA_EN_INSPECCION;
            $incidencia->notas_cierre = $diagnostico->justificacion_no_confirmada;

            $this->maquina->transicionar(
                $incidencia,
                E::Cerrada,
                $usuario,
                $observaciones ?? 'Cierre justificado - Incidencia no confirmada en inspección.',
                'CIERRE',
                ['resultado' => ResultadoCierre::NO_CONFIRMADA_EN_INSPECCION, 'diagnostico_id' => $diagnostico->id],
            );

            $this->notificador->alReportante(
                $incidencia,
                'CIERRE_NO_CONFIRMADA',
                "Resultado de la inspección de {$incidencia->codigo_ticket}",
                'La inspección técnica no confirmó el daño reportado: '.$diagnostico->justificacion_no_confirmada,
            );
        });
    }

    /**
     * Reprogramación de la corrección tras rechazo de verificación (E13 -> E10, RN-036).
     *
     * @param  array<string, mixed>  $datos
     */
    public function planificarCorreccion(Incidencia $incidencia, User $usuario, array $datos): PlanIntervencion
    {
        if (! $incidencia->estaEn(E::EnCorreccion)) {
            throw ReglaNegocioException::conflicto('Solo se pueden planificar correcciones de incidencias En corrección (E13).', 'RN-036');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos): PlanIntervencion {
            $verificacionRechazada = $incidencia->verificaciones()->where('resultado_conforme', false)->latest('id')->value('id');
            $plan = $this->crearPlan($incidencia, $usuario, $datos, null, $verificacionRechazada);

            $this->maquina->transicionar($incidencia, E::Programada, $usuario, $datos['observaciones'] ?? 'Corrección programada.', 'PLANIFICACION_CORRECCION', $this->datosPlan($plan) + ['ciclo_reproceso' => $incidencia->numero_reprocesos]);
            $this->notificarProgramacion($incidencia, $plan);

            return $plan;
        });
    }

    /**
     * Suspensión documentada (-> E18) con causa y condiciones de reanudación (RN-031).
     *
     * @param  array<string, mixed>  $datos
     */
    public function suspender(Incidencia $incidencia, User $usuario, array $datos): Suspension
    {
        if ($this->contextoRol->es(Rol::Tecnico) && (int) $incidencia->tecnico_asignado_id !== (int) $usuario->id) {
            throw new AuthorizationException('Solo el técnico asignado puede suspender la atención.');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos): Suspension {
            $suspension = $this->registrarSuspension($incidencia, $usuario, $datos['causal'], $datos['motivo_detallado'], $datos['condiciones_reanudacion']);
            $this->maquina->transicionar($incidencia, E::Suspendida, $usuario, $datos['motivo_detallado'], 'SUSPENSION', [
                'suspension_id' => $suspension->id,
                'causal' => $datos['causal'],
                'condiciones_reanudacion' => $datos['condiciones_reanudacion'],
            ]);
            $this->comunicarSuspension($incidencia, $suspension);

            return $suspension;
        });
    }

    public function registrarSuspension(Incidencia $incidencia, User $usuario, string $causal, string $motivo, string $condiciones): Suspension
    {
        return Suspension::create([
            'incidencia_id' => $incidencia->id,
            'causal' => $causal,
            'motivo_detallado' => $motivo,
            'condiciones_reanudacion' => $condiciones,
            'estado_origen' => $incidencia->estado_codigo->value,
            'usuario_suspende_id' => $usuario->id,
            'fecha_suspension' => now(),
        ]);
    }

    public function comunicarSuspension(Incidencia $incidencia, Suspension $suspension): void
    {
        $mensaje = "Atención suspendida: {$suspension->motivo_detallado}. Condición para reanudar: {$suspension->condiciones_reanudacion}";
        $this->notificador->alReportante($incidencia, 'SUSPENSION', "Tu solicitud {$incidencia->codigo_ticket} fue suspendida temporalmente", $mensaje);
        $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'SUSPENSION', "Incidencia {$incidencia->codigo_ticket} suspendida", $mensaje);
    }

    /**
     * Reanudación (E18 -> E10 o E11) conservando el tiempo y motivo de la pausa.
     *
     * @param  array<string, mixed>  $datos
     */
    public function reanudar(Incidencia $incidencia, User $usuario, array $datos): void
    {
        if (! $incidencia->estaEn(E::Suspendida)) {
            throw ReglaNegocioException::conflicto('La incidencia no está suspendida.', 'D07');
        }

        DB::transaction(function () use ($incidencia, $usuario, $datos): void {
            $destino = E::from($datos['estado_destino']);
            $suspension = $incidencia->suspensiones()->whereNull('fecha_reanudacion')->latest('id')->first();
            $minutos = $suspension === null ? null : (int) $suspension->fecha_suspension->diffInMinutes(now());

            $suspension?->forceFill([
                'usuario_reanuda_id' => $usuario->id,
                'observacion_reanudacion' => $datos['observacion'],
                'estado_destino' => $destino->value,
                'fecha_reanudacion' => now(),
            ])->save();

            if ($destino === E::Programada && isset($datos['fecha_programada'])) {
                $incidencia->planes()->where('estado_plan', PlanIntervencion::VIGENTE)->update(['estado_plan' => PlanIntervencion::REEMPLAZADO]);
                $this->crearPlan($incidencia, $usuario, $datos, $incidencia->diagnosticoActual?->id);
            }

            $this->maquina->transicionar($incidencia, $destino, $usuario, $datos['observacion'], 'REANUDACION', [
                'suspension_id' => $suspension?->id,
                'minutos_suspendida' => $minutos,
                'causal' => $suspension?->causal,
            ]);

            $this->notificador->alReportante($incidencia, 'REANUDACION', "Se reanudó la atención de {$incidencia->codigo_ticket}", $datos['observacion']);
            $this->notificador->aUsuario($incidencia, $incidencia->tecnicoAsignado, 'REANUDACION', "Se reanudó la atención de {$incidencia->codigo_ticket}", $datos['observacion']);
        });
    }

    /**
     * Anulación administrativa por imposibilidad definitiva de atención (E09/E18 -> E17, RN-031).
     */
    public function anular(Incidencia $incidencia, User $usuario, string $motivo): void
    {
        DB::transaction(function () use ($incidencia, $usuario, $motivo): void {
            $incidencia->motivo_cancelacion = $motivo;
            $incidencia->usuario_cancelacion_id = $usuario->id;
            $incidencia->resultado_final_cierre = ResultadoCierre::CANCELADA_ADMINISTRATIVAMENTE;
            $incidencia->notas_cierre = $motivo;

            $this->maquina->transicionar($incidencia, E::Cancelada, $usuario, $motivo, 'CANCELACION_ADMINISTRATIVA');
            $this->notificador->alReportante($incidencia, 'CANCELACION', "Tu solicitud {$incidencia->codigo_ticket} fue cancelada", $motivo);
        });
    }

    /**
     * Cierre administrativo por inactividad del reportante en E14 tras el plazo parametrizado (HU-9.3).
     */
    public function cierreAdministrativo(Incidencia $incidencia, User $usuario, ?string $notas): void
    {
        if ($incidencia->estaEn(E::EnAtencion, E::PendienteVerificacion, E::EnCorreccion)) {
            throw ReglaNegocioException::conflicto(
                $incidencia->estaEn(E::EnCorreccion)
                    ? 'No se puede cerrar la incidencia: existen correcciones obligatorias pendientes (RN-037).'
                    : 'No se puede cerrar una incidencia con verificaciones técnicas obligatorias pendientes (RN-037).',
                'RN-037',
            );
        }

        if (! $incidencia->estaEn(E::PendienteConfirmacion)) {
            throw ReglaNegocioException::conflicto('El cierre administrativo solo aplica a incidencias Pendientes de confirmación (E14).', 'HU-9.3');
        }

        $dias = $this->parametros->entero(Parametro::PLAZO_CONFIRMACION_DIAS_HABILES);
        $desde = CarbonImmutable::parse($incidencia->fecha_ultimo_cambio_estado ?? $incidencia->updated_at);
        $vence = $this->sla->sumarDiasHabiles($desde, $dias);

        if (now()->lessThanOrEqualTo($vence)) {
            throw new ReglaNegocioException(
                "El plazo de confirmación del reportante ({$dias} días hábiles) aún no ha vencido.",
                'HU-9.3',
                422,
                ['vence' => $vence->toIso8601String()],
            );
        }

        DB::transaction(function () use ($incidencia, $usuario, $notas): void {
            $incidencia->es_cierre_administrativo = true;
            $incidencia->resultado_final_cierre = ResultadoCierre::CIERRE_ADMINISTRATIVO;
            $incidencia->notas_cierre = $notas ?? 'Cierre administrativo por vencimiento de plazo de confirmación.';

            $this->maquina->transicionar($incidencia, E::Cerrada, $usuario, $incidencia->notas_cierre, 'CIERRE', ['resultado' => ResultadoCierre::CIERRE_ADMINISTRATIVO]);
            $this->notificador->alReportante($incidencia, 'CIERRE', "Tu solicitud {$incidencia->codigo_ticket} fue cerrada", $incidencia->notas_cierre);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearPlan(Incidencia $incidencia, User $usuario, array $datos, ?int $diagnosticoId = null, ?int $verificacionRechazadaId = null): PlanIntervencion
    {
        $incidencia->planes()->where('estado_plan', PlanIntervencion::VIGENTE)->update(['estado_plan' => PlanIntervencion::REEMPLAZADO]);

        return PlanIntervencion::create([
            'incidencia_id' => $incidencia->id,
            'diagnostico_id' => $diagnosticoId,
            'verificacion_rechazada_id' => $verificacionRechazadaId,
            'programado_por_id' => $usuario->id,
            'fecha_programada' => $datos['fecha_programada'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $datos['hora_fin'],
            'cuadrilla' => $datos['cuadrilla'] ?? null,
            'estado_plan' => PlanIntervencion::VIGENTE,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosPlan(PlanIntervencion $plan): array
    {
        return [
            'plan_id' => $plan->id,
            'fecha_programada' => $plan->fecha_programada?->toDateString(),
            'hora_inicio' => substr((string) $plan->hora_inicio, 0, 5),
            'hora_fin' => substr((string) $plan->hora_fin, 0, 5),
            'cuadrilla' => $plan->cuadrilla,
        ];
    }

    public function notificarProgramacion(Incidencia $incidencia, PlanIntervencion $plan, bool $reprogramada = false): void
    {
        $detalle = $plan->fecha_programada?->toDateString().' de '.substr((string) $plan->hora_inicio, 0, 5).' a '.substr((string) $plan->hora_fin, 0, 5);
        $titulo = ($reprogramada ? 'Intervención reprogramada' : 'Intervención programada')." en {$incidencia->codigo_ticket}";

        $this->notificador->aUsuario($incidencia, $incidencia->tecnicoAsignado, 'PROGRAMACION', $titulo, $detalle);

        if ($reprogramada) {
            $this->notificador->alReportante($incidencia, 'PROGRAMACION', $titulo, $detalle);
        }
    }

    private function diagnosticoRegistrado(Incidencia $incidencia, ?bool $confirmado = null, bool $admiteAprobado = false): Diagnostico
    {
        $diagnostico = $incidencia->diagnosticos()->latest('id')->first();
        $estadosValidos = $admiteAprobado ? [Diagnostico::REGISTRADO, Diagnostico::APROBADO] : [Diagnostico::REGISTRADO];

        if ($diagnostico === null || ! in_array($diagnostico->estado, $estadosValidos, true)) {
            throw ReglaNegocioException::conflicto('El técnico aún no ha registrado el diagnóstico de la inspección.', 'RN-023');
        }

        if ($confirmado === true && $diagnostico->incidencia_confirmada === false) {
            throw ReglaNegocioException::conflicto('El diagnóstico indica que la incidencia no se confirmó en sitio; use el cierre por no confirmación.', 'RN-024');
        }

        if ($confirmado === false && $diagnostico->incidencia_confirmada !== false) {
            throw ReglaNegocioException::conflicto('El técnico no registró la incidencia como no confirmada en la inspección.', 'RN-024');
        }

        return $diagnostico;
    }

    private function validarTecnico(Incidencia $incidencia, ?User $tecnico): void
    {
        if ($tecnico === null || ! $tecnico->activo || ! $tecnico->tieneRol(Rol::Tecnico)) {
            throw ValidationException::withMessages(['tecnico_id' => 'El usuario seleccionado no es un técnico de mantenimiento (R04) activo.']);
        }

        $especialidadId = $incidencia->categoria()->value('especialidad_id');

        if ($especialidadId !== null && ! $tecnico->especialidades()->whereKey($especialidadId)->exists()) {
            throw ValidationException::withMessages(['tecnico_id' => 'El técnico no está autorizado para la especialidad de la categoría de la incidencia.']);
        }
    }
}
