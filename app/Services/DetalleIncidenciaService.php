<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\EstadoIncidencia as E;
use App\Http\Resources\EventoAuditoriaResource;
use App\Http\Resources\EvidenciaResource;
use App\Http\Resources\UbicacionIncidencia;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\User;
use App\Support\ResultadoCierre;
use Illuminate\Database\Eloquent\Collection;

/**
 * Construye la ficha de detalle de una incidencia para el reportante (HU-4.2) y para el personal operativo.
 */
class DetalleIncidenciaService
{
    public function __construct(
        private ReportanteService $reportante,
        private MaquinaEstados $maquina,
        private CalculadoraSla $sla,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paraReportante(Incidencia $incidencia, User $usuario): array
    {
        $incidencia->loadMissing([
            'estado', 'categoria', 'subcategoria', 'sede', 'edificio', 'piso', 'espacioFisico', 'prioridad',
            'incidenciaPrincipal.estado', 'aclaracionPendiente', 'diagnosticoActual',
            'evidencias' => fn ($query) => $query->orderBy('id'),
            'evidencias.usuarioCarga',
        ]);

        $esCreador = $incidencia->esCreadaPor($usuario);
        $reapertura = $this->reportante->evaluarReapertura($incidencia);
        $noConfirmada = $incidencia->resultado_final_cierre === ResultadoCierre::NO_CONFIRMADA_EN_INSPECCION;
        $etapasVisibles = $noConfirmada ? ['REPORTE', 'ACLARACION', 'INSPECCION'] : ['REPORTE', 'ACLARACION', 'EJECUCION'];

        return array_merge($this->base($incidencia), [
            'es_creador' => $esCreador,
            'es_seguimiento' => ! $esCreador,
            'evidencias' => EvidenciaResource::collection($incidencia->evidencias->whereIn('etapa', $etapasVisibles)->values()),
            'aclaracion_pendiente' => $incidencia->estaEn(E::PendienteInformacion) && $incidencia->aclaracionPendiente !== null ? [
                'id' => $incidencia->aclaracionPendiente->id,
                'pregunta' => $incidencia->aclaracionPendiente->pregunta_gestor,
                'fecha_solicitud' => $incidencia->aclaracionPendiente->fecha_solicitud?->toIso8601String(),
                'fecha_limite_respuesta' => $incidencia->aclaracionPendiente->fecha_limite_respuesta?->toIso8601String(),
            ] : null,
            'incidencia_principal' => $this->principal($incidencia),
            'resultado_cierre' => $incidencia->estaEn(E::Cerrada, E::NoProcedente, E::Cancelada) ? [
                'resultado' => $incidencia->resultado_final_cierre,
                'fecha_cierre' => $incidencia->fecha_cierre?->toIso8601String(),
                'notas' => $incidencia->notas_cierre ?? $incidencia->motivo_cancelacion,
                'justificacion_tecnica' => $noConfirmada ? $incidencia->diagnosticoActual?->justificacion_no_confirmada : null,
                'hallazgos' => $noConfirmada ? $incidencia->diagnosticoActual?->hallazgos : null,
                'calificacion' => $incidencia->calificacion_servicio,
            ] : null,
            'historial' => EventoAuditoriaResource::collection($this->historialPublico($incidencia)),
            'acciones' => [
                'puede_cancelar' => $esCreador && $incidencia->estaEn(E::Registrada, E::PendienteInformacion, E::EnRevision, E::EnClasificacion),
                'mensaje_cancelacion' => $incidencia->estaEn(E::Registrada, E::PendienteInformacion, E::EnRevision, E::EnClasificacion) || $incidencia->estado_codigo->esTerminal()
                    ? null
                    : 'La solicitud ya cuenta con personal técnico asignado. Para novedades comuníquese con Coordinación de Mantenimiento',
                'puede_responder_aclaracion' => $esCreador && $incidencia->estaEn(E::PendienteInformacion),
                'puede_confirmar' => $esCreador && $incidencia->estaEn(E::PendienteConfirmacion),
                'puede_reabrir' => $esCreador && $reapertura['permitido'],
                'reapertura' => $reapertura,
                'puede_crear_reporte_basado' => $esCreador && $incidencia->estaEn(E::Cerrada) && ! $reapertura['permitido'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function paraOperador(Incidencia $incidencia): array
    {
        $incidencia->loadMissing([
            'estado', 'categoria.especialidad', 'subcategoria', 'sede', 'edificio', 'piso', 'espacioFisico', 'prioridad',
            'creador', 'tecnicoAsignado', 'incidenciaPrincipal.estado', 'duplicados',
            'solicitudesAclaracion.gestor', 'diagnosticos.tecnico', 'solicitudesRecursos.detalles', 'solicitudesRecursos.seguimientos.usuario',
            'planes.reprogramaciones', 'actividades.materiales', 'actividades.tecnicoEjecutor', 'suspensiones', 'verificaciones.verificador',
            'evaluacionesRiesgo.evaluador', 'asignaciones.tecnico', 'asignaciones.asignadoPor',
            'evidencias' => fn ($query) => $query->orderBy('id'),
            'evidencias.usuarioCarga',
        ]);

        return array_merge($this->base($incidencia), [
            'reportante' => $incidencia->creador?->only(['id', 'name', 'email']),
            'tecnico_asignado' => $incidencia->tecnicoAsignado?->only(['id', 'name', 'email']),
            'impacto' => $incidencia->impacto,
            'urgencia' => $incidencia->urgencia,
            'protocolo_emergencia_activo' => $incidencia->protocolo_emergencia_activo,
            'descripcion_riesgo' => $incidencia->descripcion_riesgo,
            'numero_reaperturas' => $incidencia->numero_reaperturas,
            'numero_reprocesos' => $incidencia->numero_reprocesos,
            'resultado_final_cierre' => $incidencia->resultado_final_cierre,
            'incidencia_principal' => $this->principal($incidencia),
            'duplicados_vinculados' => $incidencia->duplicados->map(fn (Incidencia $duplicado) => $duplicado->only(['id', 'codigo_ticket', 'resumen']))->values(),
            'sla' => [
                'fecha_limite_respuesta' => $incidencia->fecha_limite_respuesta?->toIso8601String(),
                'fecha_primera_respuesta' => $incidencia->fecha_primera_respuesta?->toIso8601String(),
                'sla_respuesta_cumplido' => $incidencia->sla_respuesta_cumplido,
                'resolucion' => $this->sla->evaluarResolucion($incidencia),
            ],
            'aclaraciones' => $incidencia->solicitudesAclaracion,
            'evaluaciones_riesgo' => $incidencia->evaluacionesRiesgo,
            'asignaciones' => $incidencia->asignaciones,
            'diagnosticos' => $incidencia->diagnosticos,
            'solicitudes_recursos' => $incidencia->solicitudesRecursos,
            'planes_intervencion' => $incidencia->planes,
            'actividades' => $incidencia->actividades,
            'suspensiones' => $incidencia->suspensiones,
            'verificaciones' => $incidencia->verificaciones,
            'evidencias' => EvidenciaResource::collection($incidencia->evidencias),
            'transiciones_disponibles' => $this->maquina->destinosDisponibles($incidencia),
        ]);
    }

    /**
     * Línea de tiempo visible para el reportante: sin registros forenses ni notas internas (HU-10.2 escenario 5).
     *
     * @return Collection<int, EventoAuditoria>
     */
    public function historialPublico(Incidencia $incidencia): Collection
    {
        return $incidencia->eventos()
            ->where('visibilidad', EventoAuditoria::PUBLICO)
            ->where('categoria', CategoriaEvento::Estado->value)
            ->orderBy('fecha_hora')
            ->get()
            ->each(function (EventoAuditoria $evento): void {
                $evento->datos = null;
                $evento->setRelation('usuario', null);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function base(Incidencia $incidencia): array
    {
        return [
            'id' => $incidencia->id,
            'codigo_ticket' => $incidencia->codigo_ticket,
            'resumen' => $incidencia->resumen,
            'descripcion' => $incidencia->descripcion,
            'canal_radicacion' => $incidencia->canal_radicacion,
            'estado' => [
                'codigo' => $incidencia->estado_codigo->value,
                'nombre' => $incidencia->estado_codigo->nombre(),
                'color' => $incidencia->estado?->color,
                'significado' => $incidencia->estado?->significado_publico,
                'requiere_accion_reportante' => $incidencia->estado?->requiere_accion_reportante,
            ],
            'categoria' => $incidencia->categoria?->only(['id', 'codigo', 'nombre']),
            'subcategoria' => $incidencia->subcategoria?->only(['id', 'codigo', 'nombre']),
            'prioridad' => $incidencia->prioridad?->only(['id', 'codigo', 'nombre', 'color']),
            'ubicacion' => UbicacionIncidencia::de($incidencia),
            'es_riesgo_inmediato' => $incidencia->es_riesgo_inmediato,
            'posible_duplicado' => $incidencia->posible_duplicado,
            'fecha_registro' => $incidencia->created_at?->toIso8601String(),
            'fecha_actualizacion' => $incidencia->updated_at?->toIso8601String(),
            'fecha_cierre' => $incidencia->fecha_cierre?->toIso8601String(),
        ];
    }

    /**
     * Referencia a la incidencia principal cuando el reporte fue unificado como duplicado (RN-015).
     *
     * @return array<string, mixed>|null
     */
    private function principal(Incidencia $incidencia): ?array
    {
        $principal = $incidencia->incidenciaPrincipal;

        if ($principal === null) {
            return null;
        }

        return [
            'id' => $principal->id,
            'codigo_ticket' => $principal->codigo_ticket,
            'resumen' => $principal->resumen,
            'estado' => ['codigo' => $principal->estado_codigo->value, 'nombre' => $principal->estado_codigo->nombre()],
            'fecha_actualizacion' => $principal->updated_at?->toIso8601String(),
        ];
    }
}
