<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\EstadoIncidencia as E;
use App\Enums\EtapaEvidencia;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\ActividadMantenimiento;
use App\Models\Diagnostico;
use App\Models\Incidencia;
use App\Models\PlanIntervencion;
use App\Models\User;
use App\Support\ContextoRol;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trabajo de campo del técnico asignado: inspección y diagnóstico (HU-7.1, HU-7.2) y ejecución (HU-7.3).
 */
class EjecucionService
{
    public function __construct(
        private MaquinaEstados $maquina,
        private AuditoriaService $auditoria,
        private Notificador $notificador,
        private EvidenciaService $evidencias,
        private ContextoRol $contextoRol,
    ) {}

    public function iniciarInspeccion(Incidencia $incidencia, User $usuario): Diagnostico
    {
        $this->exigirTecnicoAsignado($incidencia, $usuario);

        return DB::transaction(function () use ($incidencia, $usuario): Diagnostico {
            $this->maquina->transicionar($incidencia, E::EnDiagnostico, $usuario, 'Inicio de inspección en sitio.', 'INICIO_INSPECCION');

            return Diagnostico::create([
                'incidencia_id' => $incidencia->id,
                'tecnico_id' => $usuario->id,
                'fecha_inicio_inspeccion' => now(),
                'estado' => Diagnostico::EN_INSPECCION,
            ]);
        });
    }

    /**
     * Diagnóstico de incidencia confirmada (RN-022, RN-023). Habilita la evaluación del coordinador.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrarDiagnostico(Incidencia $incidencia, User $usuario, array $datos): Diagnostico
    {
        $diagnostico = $this->diagnosticoEnCurso($incidencia, $usuario);

        return DB::transaction(function () use ($incidencia, $usuario, $datos, $diagnostico): Diagnostico {
            $diagnostico->forceFill([
                'incidencia_confirmada' => true,
                'hallazgos' => $datos['hallazgos'],
                'descripcion_problema' => $datos['descripcion_problema'],
                'alcance' => $datos['alcance'] ?? null,
                'intervencion_propuesta' => $datos['intervencion_propuesta'],
                'requiere_recursos' => (bool) ($datos['requiere_recursos'] ?? false),
                'requiere_autorizacion' => (bool) ($datos['requiere_autorizacion'] ?? false),
                'fecha_fin_diagnostico' => now(),
                'estado' => Diagnostico::REGISTRADO,
            ])->save();

            $evidencias = $this->evidencias->vincularPendientes(array_map('intval', $datos['evidencias'] ?? []), $usuario, $incidencia, EtapaEvidencia::Inspeccion, 'diagnostico', $diagnostico->id);

            $this->auditoria->registrar(
                tipoEvento: 'DIAGNOSTICO',
                categoria: CategoriaEvento::Actividad,
                incidencia: $incidencia,
                usuario: $usuario,
                motivo: $datos['descripcion_problema'],
                datos: [
                    'diagnostico_id' => $diagnostico->id,
                    'requiere_recursos' => $diagnostico->requiere_recursos,
                    'requiere_autorizacion' => $diagnostico->requiere_autorizacion,
                ],
                evidencias: $this->evidencias->paraAuditoria($evidencias),
            );

            $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'DIAGNOSTICO_REGISTRADO', "Diagnóstico registrado en {$incidencia->codigo_ticket}", $datos['intervencion_propuesta']);

            return $diagnostico;
        });
    }

    /**
     * Descarte técnico justificado (RN-024): queda pendiente del visto bueno del coordinador (cerrar-no-confirmada).
     *
     * @param  array<string, mixed>  $datos
     */
    public function descartarPorInspeccion(Incidencia $incidencia, User $usuario, array $datos): Diagnostico
    {
        $diagnostico = $this->diagnosticoEnCurso($incidencia, $usuario);

        return DB::transaction(function () use ($incidencia, $usuario, $datos, $diagnostico): Diagnostico {
            $evidencias = $this->evidencias->vincularPendientes(array_map('intval', $datos['evidencias'] ?? []), $usuario, $incidencia, EtapaEvidencia::Inspeccion, 'diagnostico', $diagnostico->id);

            if ($incidencia->evidencias()->where('etapa', EtapaEvidencia::Inspeccion->value)->where('created_at', '>=', $diagnostico->fecha_inicio_inspeccion)->doesntExist()) {
                throw ValidationException::withMessages([
                    'evidencias' => 'Debe adjuntar evidencia fotográfica del estado funcional encontrado en sitio (RN-024).',
                ]);
            }

            $diagnostico->forceFill([
                'incidencia_confirmada' => false,
                'hallazgos' => $datos['hallazgos'],
                'justificacion_no_confirmada' => $datos['justificacion'],
                'fecha_fin_diagnostico' => now(),
                'estado' => Diagnostico::REGISTRADO,
            ])->save();

            $this->auditoria->registrar(
                tipoEvento: 'DESCARTE_POR_INSPECCION',
                categoria: CategoriaEvento::Actividad,
                incidencia: $incidencia,
                usuario: $usuario,
                motivo: $datos['justificacion'],
                datos: ['diagnostico_id' => $diagnostico->id, 'hallazgos' => $datos['hallazgos']],
                evidencias: $this->evidencias->paraAuditoria($evidencias),
            );

            $this->notificador->aRoles($incidencia, [Rol::Coordinador], 'NO_CONFIRMADA', "Incidencia no confirmada en inspección: {$incidencia->codigo_ticket}", 'El técnico documentó que el daño no existe. Se requiere su visto bueno para el cierre justificado.');

            return $diagnostico;
        });
    }

    public function iniciarAtencion(Incidencia $incidencia, User $usuario): ActividadMantenimiento
    {
        $this->exigirTecnicoAsignado($incidencia, $usuario);

        return DB::transaction(function () use ($incidencia, $usuario): ActividadMantenimiento {
            $this->maquina->transicionar($incidencia, E::EnAtencion, $usuario, 'Inicio de la intervención física.', 'INICIO_ATENCION');

            return ActividadMantenimiento::create([
                'incidencia_id' => $incidencia->id,
                'plan_intervencion_id' => $incidencia->planVigente()->value('id'),
                'tecnico_ejecutor_id' => $usuario->id,
                'fecha_inicio' => now(),
            ]);
        });
    }

    /**
     * Registro de la ejecución con actividades, tiempo y materiales (RN-032) y evidencias obligatorias (RN-033): E11 -> E12.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrarIntervencion(Incidencia $incidencia, User $usuario, array $datos): ActividadMantenimiento
    {
        $this->exigirTecnicoAsignado($incidencia, $usuario);

        if (! $incidencia->estaEn(E::EnAtencion)) {
            throw ReglaNegocioException::conflicto('La incidencia debe estar En atención (E11) para registrar la intervención.', 'D07');
        }

        $actividad = $incidencia->actividades()->whereNull('fecha_fin')->latest('id')->first();

        if ($actividad === null) {
            throw ReglaNegocioException::conflicto('No hay una intervención iniciada para registrar.', 'RN-032');
        }

        return DB::transaction(function () use ($incidencia, $usuario, $datos, $actividad): ActividadMantenimiento {
            $evidencias = $this->evidencias->vincularPendientes(array_map('intval', $datos['evidencias'] ?? []), $usuario, $incidencia, EtapaEvidencia::Ejecucion, 'actividad_mantenimiento', $actividad->id);

            $tieneEvidencias = $incidencia->evidencias()
                ->where('etapa', EtapaEvidencia::Ejecucion->value)
                ->where(fn ($query) => $query->where('referencia_id', $actividad->id)->orWhere('created_at', '>=', $actividad->fecha_inicio))
                ->exists();

            if (! $tieneEvidencias) {
                throw ValidationException::withMessages([
                    'evidencias' => 'Debe adjuntar las evidencias obligatorias del trabajo realizado antes de solicitar la verificación (RN-033)',
                ]);
            }

            $actividad->forceFill([
                'fecha_fin' => now(),
                'tiempo_invertido_minutos' => $datos['tiempo_invertido_minutos'],
                'actividades_realizadas' => $datos['actividades_realizadas'],
                'dificultades_observaciones' => $datos['dificultades_observaciones'] ?? null,
            ])->save();

            $actividad->materiales()->createMany($datos['materiales'] ?? []);
            $incidencia->planes()->where('estado_plan', PlanIntervencion::VIGENTE)->update(['estado_plan' => PlanIntervencion::EJECUTADO]);

            $this->maquina->transicionar(
                $incidencia,
                E::PendienteVerificacion,
                $usuario,
                $datos['actividades_realizadas'],
                'EJECUCION_REGISTRADA',
                ['actividad_id' => $actividad->id, 'tiempo_invertido_minutos' => $actividad->tiempo_invertido_minutos],
                evidencias: $this->evidencias->paraAuditoria($evidencias),
            );

            $this->notificador->aRoles($incidencia, [Rol::Verificador], 'PENDIENTE_VERIFICACION', "Intervención lista para verificación: {$incidencia->codigo_ticket}", $datos['actividades_realizadas']);

            return $actividad;
        });
    }

    private function diagnosticoEnCurso(Incidencia $incidencia, User $usuario): Diagnostico
    {
        $this->exigirTecnicoAsignado($incidencia, $usuario);

        if (! $incidencia->estaEn(E::EnDiagnostico)) {
            throw ReglaNegocioException::conflicto('La incidencia debe estar En diagnóstico (E07).', 'D07');
        }

        $diagnostico = $incidencia->diagnosticos()->where('estado', Diagnostico::EN_INSPECCION)->latest('id')->first();

        if ($diagnostico === null) {
            throw ReglaNegocioException::conflicto('No hay una inspección en curso o el diagnóstico ya fue registrado.', 'RN-022');
        }

        return $diagnostico;
    }

    /**
     * @throws AuthorizationException
     */
    private function exigirTecnicoAsignado(Incidencia $incidencia, User $usuario): void
    {
        if ($this->contextoRol->es(Rol::Administrador)) {
            return;
        }

        if ((int) $incidencia->tecnico_asignado_id !== (int) $usuario->id) {
            throw new AuthorizationException('Solo el técnico responsable asignado puede registrar la inspección o la intervención de esta incidencia.');
        }
    }
}
