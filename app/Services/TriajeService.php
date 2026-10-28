<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\CodigoPrioridad;
use App\Enums\EstadoIncidencia as E;
use App\Enums\Impacto;
use App\Enums\Rol;
use App\Enums\Urgencia;
use App\Exceptions\ReglaNegocioException;
use App\Models\DuplicadoIncidencia;
use App\Models\EvaluacionRiesgo;
use App\Models\HistorialCambioPrioridad;
use App\Models\Incidencia;
use App\Models\Prioridad;
use App\Models\SolicitudAclaracion;
use App\Models\User;
use App\Support\Parametro;
use App\Support\ResultadoCierre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Triaje del gestor (E05): revisión, aclaraciones (RN-006..RN-009), duplicados (RN-011..RN-015),
 * procedencia y prioridad ITIL (RN-019, RN-020) y evaluación de riesgo inmediato (RN-016..RN-018).
 */
class TriajeService
{
    public function __construct(
        private MaquinaEstados $maquina,
        private AuditoriaService $auditoria,
        private Notificador $notificador,
        private ParametrosService $parametros,
        private CatalogoService $catalogo,
        private CalculadoraSla $sla,
    ) {}

    public function iniciarRevision(Incidencia $incidencia, User $usuario): void
    {
        DB::transaction(fn () => $this->maquina->transicionar($incidencia, E::EnRevision, $usuario, 'Inicio de revisión por el gestor.', 'INICIO_REVISION'));
    }

    public function solicitarAclaracion(Incidencia $incidencia, User $usuario, string $pregunta): SolicitudAclaracion
    {
        return DB::transaction(function () use ($incidencia, $usuario, $pregunta): SolicitudAclaracion {
            $ahora = now();
            $aclaracion = SolicitudAclaracion::create([
                'incidencia_id' => $incidencia->id,
                'gestor_solicitante_id' => $usuario->id,
                'pregunta_gestor' => $pregunta,
                'fecha_solicitud' => $ahora,
                'fecha_limite_respuesta' => $this->sla->sumarDiasHabiles($ahora, $this->parametros->entero(Parametro::PLAZO_ACLARACION_DIAS_HABILES)),
                'estado' => SolicitudAclaracion::PENDIENTE,
            ]);

            $this->maquina->transicionar(
                $incidencia,
                E::PendienteInformacion,
                $usuario,
                $pregunta,
                'SOLICITUD_ACLARACION',
                ['solicitud_aclaracion_id' => $aclaracion->id, 'fecha_limite_respuesta' => $aclaracion->fecha_limite_respuesta->toIso8601String()],
            );

            $this->notificador->alReportante(
                $incidencia,
                'ACLARACION_SOLICITADA',
                "Tu solicitud #{$incidencia->codigo_ticket} requiere información adicional",
                $pregunta,
            );

            return $aclaracion;
        });
    }

    /**
     * Candidatos a duplicado ponderando ubicación, categoría, similitud de descripción y estado abierto (RN-011).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function posiblesDuplicados(Incidencia $incidencia): Collection
    {
        $incidencia->loadMissing('piso');
        $estados = E::valores(E::activosParaDuplicados());

        return Incidencia::query()
            ->whereKeyNot($incidencia->id)
            ->whereIn('estado_codigo', [...$estados, E::PendienteVerificacion->value, E::EnCorreccion->value, E::PendienteConfirmacion->value, E::Suspendida->value])
            ->whereNull('incidencia_principal_id')
            ->where(fn ($query) => $query->where('espacio_fisico_id', $incidencia->espacio_fisico_id)
                ->orWhere(fn ($mismoPiso) => $mismoPiso->where('piso_id', $incidencia->piso_id)->where('categoria_id', $incidencia->categoria_id)))
            ->where('created_at', '>=', now()->subDays(max(30, $this->parametros->entero(Parametro::VENTANA_DUPLICADOS_DIAS))))
            ->with(['categoria', 'espacioFisico', 'evidencias'])
            ->limit(50)
            ->get()
            ->map(function (Incidencia $candidata) use ($incidencia): array {
                similar_text(Str::lower($incidencia->descripcion), Str::lower($candidata->descripcion), $similitud);
                $mismoEspacio = (int) $candidata->espacio_fisico_id === (int) $incidencia->espacio_fisico_id;
                $mismaCategoria = (int) $candidata->categoria_id === (int) $incidencia->categoria_id;
                $puntaje = ($mismoEspacio ? 50 : 20) + ($mismaCategoria ? 30 : 0) + round($similitud * 0.2, 1);

                return [
                    'id' => $candidata->id,
                    'codigo_ticket' => $candidata->codigo_ticket,
                    'resumen' => $candidata->resumen,
                    'descripcion' => $candidata->descripcion,
                    'estado' => ['codigo' => $candidata->estado_codigo->value, 'nombre' => $candidata->estado_codigo->nombre()],
                    'categoria' => $candidata->categoria->only(['id', 'nombre']),
                    'espacio' => $candidata->espacioFisico->only(['id', 'nombre']),
                    'fecha_registro' => $candidata->created_at?->toIso8601String(),
                    'evidencias' => $candidata->evidencias->count(),
                    'criterios' => [
                        'misma_ubicacion' => $mismoEspacio,
                        'misma_categoria' => $mismaCategoria,
                        'similitud_descripcion' => round($similitud, 1),
                    ],
                    'puntaje' => $puntaje,
                ];
            })
            ->sortByDesc('puntaje')
            ->values();
    }

    /**
     * Confirmación humana del duplicado (RN-013): vincula a la principal, conserva el origen (RN-014)
     * y cierra el reporte como "Vinculada por duplicidad".
     */
    public function vincularDuplicado(Incidencia $incidencia, User $usuario, string $principalId, ?string $observaciones): void
    {
        if ($incidencia->id === $principalId) {
            throw ValidationException::withMessages(['incidencia_principal_id' => 'Una incidencia no puede vincularse consigo misma. Seleccione una incidencia principal válida.']);
        }

        $principal = Incidencia::query()->find($principalId);

        if ($principal === null || $principal->estaEn(E::NoProcedente, E::Cancelada) || $principal->incidencia_principal_id !== null) {
            throw ValidationException::withMessages(['incidencia_principal_id' => 'Seleccione una incidencia principal válida (no puede estar no procedente, cancelada ni ser a su vez un duplicado).']);
        }

        if (! $incidencia->estaEn(E::Registrada, E::EnRevision, E::EnClasificacion)) {
            throw ReglaNegocioException::conflicto('Solo se pueden vincular como duplicadas incidencias en revisión o clasificación.', 'RN-013');
        }

        DB::transaction(function () use ($incidencia, $usuario, $principal, $observaciones): void {
            DuplicadoIncidencia::create([
                'incidencia_principal_id' => $principal->id,
                'incidencia_duplicada_id' => $incidencia->id,
                'usuario_confirmacion_id' => $usuario->id,
                'observaciones' => $observaciones,
                'fecha_vinculacion' => now(),
            ]);

            $incidencia->incidencia_principal_id = $principal->id;
            $incidencia->posible_duplicado = false;
            $incidencia->resultado_final_cierre = ResultadoCierre::VINCULADA_POR_DUPLICIDAD;
            $incidencia->notas_cierre = $observaciones;

            $this->maquina->transicionar(
                $incidencia,
                E::Cerrada,
                $usuario,
                $observaciones ?? "Vinculada como duplicado de {$principal->codigo_ticket}.",
                'VINCULACION_DUPLICADO',
                ['incidencia_principal_id' => $principal->id, 'codigo_principal' => $principal->codigo_ticket],
            );

            $principal->seguidores()->syncWithoutDetaching([$incidencia->usuario_creador_id => ['fecha_suscripcion' => now()]]);

            $this->auditoria->registrar(
                tipoEvento: 'DUPLICADO_VINCULADO',
                categoria: CategoriaEvento::Forense,
                incidencia: $principal,
                usuario: $usuario,
                motivo: "Se vinculó {$incidencia->codigo_ticket} como duplicado.",
                datos: ['incidencia_duplicada_id' => $incidencia->id],
            );

            $this->notificador->alReportante(
                $incidencia,
                'DUPLICADO_VINCULADO',
                "Tu solicitud {$incidencia->codigo_ticket} fue unificada",
                "Tu reporte corresponde al mismo daño de {$principal->codigo_ticket}. Puedes consultar su avance desde tu solicitud.",
            );
        });
    }

    public function descartarDuplicado(Incidencia $incidencia, User $usuario, ?string $observaciones): void
    {
        DB::transaction(function () use ($incidencia, $usuario, $observaciones): void {
            $incidencia->forceFill(['posible_duplicado' => false])->save();

            $this->auditoria->registrar(
                tipoEvento: 'DUPLICADO_DESCARTADO',
                categoria: CategoriaEvento::Forense,
                incidencia: $incidencia,
                usuario: $usuario,
                motivo: $observaciones ?? 'No es duplicado: continúa revisión independiente.',
            );
        });
    }

    /**
     * Procedencia y prioridad ITIL: no procedente -> E16; procedente -> (E04) -> E05 con prioridad calculada.
     *
     * @param  array<string, mixed>  $datos
     */
    public function clasificar(Incidencia $incidencia, User $usuario, array $datos): void
    {
        if (! $incidencia->estaEn(E::EnRevision, E::EnClasificacion)) {
            throw ReglaNegocioException::conflicto('La incidencia debe estar En revisión o En clasificación para clasificarse.', 'D07');
        }

        DB::transaction(function () use ($incidencia, $usuario, $datos): void {
            if (! $datos['procedente']) {
                $incidencia->resultado_final_cierre = ResultadoCierre::NO_PROCEDENTE;
                $incidencia->notas_cierre = $datos['motivo'];
                $this->maquina->transicionar($incidencia, E::NoProcedente, $usuario, $datos['motivo'], 'NO_PROCEDENTE');
                $this->notificador->alReportante($incidencia, 'NO_PROCEDENTE', "Tu solicitud {$incidencia->codigo_ticket} no es procedente", $datos['motivo']);

                return;
            }

            if ($incidencia->estaEn(E::EnRevision)) {
                $this->maquina->transicionar($incidencia, E::EnClasificacion, $usuario, 'Información completa y procedente.', 'VALIDACION_PROCEDENCIA');
            }

            $impacto = Impacto::from($datos['impacto']);
            $urgencia = Urgencia::from($datos['urgencia']);
            $this->aplicarPrioridad($incidencia, $usuario, $impacto, $urgencia, $datos['justificacion'] ?? null, 'CLASIFICACION');

            $this->maquina->transicionar(
                $incidencia,
                E::PendienteAsignacion,
                $usuario,
                'Remitida a coordinación para asignación.',
                'REMISION_COORDINACION',
                ['impacto' => $impacto->value, 'urgencia' => $urgencia->value, 'prioridad' => $incidencia->prioridad?->codigo],
            );

            $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'PENDIENTE_ASIGNACION', "Nueva incidencia por asignar: {$incidencia->codigo_ticket}", "Prioridad {$incidencia->prioridad?->nombre}. {$incidencia->resumen}");
        });
    }

    /**
     * Reevaluación de prioridad con justificación (RN-020).
     */
    public function cambiarPrioridad(Incidencia $incidencia, User $usuario, Impacto $impacto, Urgencia $urgencia, string $justificacion): void
    {
        if ($incidencia->estado_codigo->esTerminal() || $incidencia->prioridad_id === null) {
            throw ReglaNegocioException::conflicto('Solo se puede cambiar la prioridad de incidencias clasificadas y en curso.', 'RN-020');
        }

        DB::transaction(fn () => $this->aplicarPrioridad($incidencia, $usuario, $impacto, $urgencia, $justificacion, 'REEVALUACION'));
    }

    /**
     * Evaluación de riesgo (RN-016) con protocolo de emergencia y salto de cola (RN-017, RN-018).
     *
     * @param  array<string, mixed>  $datos
     */
    public function evaluarRiesgo(Incidencia $incidencia, User $usuario, array $datos): EvaluacionRiesgo
    {
        if ($incidencia->estado_codigo->esTerminal()) {
            throw ReglaNegocioException::conflicto('La incidencia ya está finalizada.');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos): EvaluacionRiesgo {
            $riesgo = (bool) $datos['tiene_riesgo_inmediato'];

            $evaluacion = EvaluacionRiesgo::create([
                'incidencia_id' => $incidencia->id,
                'evaluador_id' => $usuario->id,
                'tiene_riesgo_inmediato' => $riesgo,
                'nivel_riesgo' => $datos['nivel_riesgo'],
                'protocolo_activado' => $riesgo,
                'acciones_adoptadas' => $datos['acciones_adoptadas'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'created_at' => now(),
            ]);

            $this->auditoria->registrar(
                tipoEvento: $riesgo ? 'PROTOCOLO_EMERGENCIA_ACTIVADO' : 'RIESGO_EVALUADO',
                categoria: CategoriaEvento::Forense,
                incidencia: $incidencia,
                usuario: $usuario,
                motivo: $datos['acciones_adoptadas'] ?? $datos['observaciones'] ?? 'Sin riesgo inmediato.',
                datos: ['evaluacion_id' => $evaluacion->id, 'nivel_riesgo' => $evaluacion->nivel_riesgo, 'riesgo_inmediato' => $riesgo],
            );

            if (! $riesgo) {
                return $evaluacion;
            }

            $incidencia->es_riesgo_inmediato = true;
            $incidencia->protocolo_emergencia_activo = true;
            $this->aplicarPrioridad($incidencia, $usuario, Impacto::Alto, Urgencia::Alta, 'Protocolo de riesgo inmediato activado (RN-017).', 'PROTOCOLO_EMERGENCIA', CodigoPrioridad::Critica);

            foreach ([E::EnRevision, E::EnClasificacion, E::PendienteAsignacion] as $siguiente) {
                if ($this->maquina->permite($incidencia->estado_codigo, $siguiente, Rol::Gestor->value) && $incidencia->estaEn(E::Registrada, E::EnRevision, E::EnClasificacion)) {
                    $this->maquina->transicionar($incidencia, $siguiente, $usuario, 'Vía rápida por protocolo de emergencia (RN-017).', 'PROTOCOLO_EMERGENCIA');
                }
            }

            $this->notificador->aRoles(
                $incidencia,
                [Rol::Coordinador],
                'RIESGO_INMEDIATO',
                "URGENTE: protocolo de emergencia en {$incidencia->codigo_ticket}",
                "Acciones adoptadas: {$evaluacion->acciones_adoptadas}",
            );

            if ($incidencia->tecnico_asignado_id !== null) {
                $this->notificador->aUsuario($incidencia, $incidencia->tecnicoAsignado, 'RIESGO_INMEDIATO', "URGENTE: protocolo de emergencia en {$incidencia->codigo_ticket}", "Acciones adoptadas: {$evaluacion->acciones_adoptadas}");
            }

            return $evaluacion;
        });
    }

    private function aplicarPrioridad(
        Incidencia $incidencia,
        User $usuario,
        Impacto $impacto,
        Urgencia $urgencia,
        ?string $justificacion,
        string $origen,
        ?CodigoPrioridad $forzar = null,
    ): void {
        $nueva = $forzar !== null ? $this->catalogo->prioridadPorCodigo($forzar->value) : $this->catalogo->prioridadPara($impacto, $urgencia);

        if ($nueva === null) {
            throw new ReglaNegocioException('La matriz de prioridad no tiene configurada la combinación indicada.', 'RN-019');
        }

        if ($incidencia->protocolo_emergencia_activo && $nueva->codigo !== CodigoPrioridad::Critica->value) {
            $nueva = $this->catalogo->prioridadPorCodigo(CodigoPrioridad::Critica->value);
        }

        /** @var Prioridad|null $anterior */
        $anterior = $incidencia->prioridad;

        HistorialCambioPrioridad::create([
            'incidencia_id' => $incidencia->id,
            'prioridad_anterior_id' => $anterior?->id,
            'prioridad_nueva_id' => $nueva->id,
            'impacto' => $impacto->value,
            'urgencia' => $urgencia->value,
            'origen' => $origen,
            'usuario_id' => $usuario->id,
            'justificacion' => $justificacion,
            'created_at' => now(),
        ]);

        $incidencia->impacto = $impacto->value;
        $incidencia->urgencia = $urgencia->value;
        $incidencia->prioridad_id = $nueva->id;
        $incidencia->setRelation('prioridad', $nueva);

        $limiteRespuesta = $incidencia->fecha_limite_respuesta;
        $this->sla->fijarMetas($incidencia);

        if ($incidencia->fecha_primera_respuesta !== null) {
            $incidencia->fecha_limite_respuesta = $limiteRespuesta;
        }

        $incidencia->save();

        $this->auditoria->registrar(
            tipoEvento: 'CAMBIO_PRIORIDAD',
            categoria: CategoriaEvento::Forense,
            incidencia: $incidencia,
            usuario: $usuario,
            motivo: $justificacion,
            datos: [
                'origen' => $origen,
                'prioridad_anterior' => $anterior?->codigo,
                'prioridad_nueva' => $nueva->codigo,
                'impacto' => $impacto->value,
                'urgencia' => $urgencia->value,
            ],
        );
    }
}
