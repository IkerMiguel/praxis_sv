<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\EstadoIncidencia as E;
use App\Enums\EstadoSolicitudRecursos;
use App\Enums\Rol as R;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\TransicionNoPermitidaException;
use App\Models\Incidencia;
use App\Models\User;
use App\Support\ContextoRol;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Máquina de estados central de la incidencia. Valida cada transición contra la matriz D07
 * (ampliada con las bifurcaciones que definen las HU) y los invariantes RN-021, RN-029 y RN-037.
 */
class MaquinaEstados
{
    /**
     * Pseudorol usado por procesos automáticos (scheduler).
     */
    public const SISTEMA = 'SISTEMA';

    public function __construct(
        private ContextoRol $contextoRol,
        private AuditoriaService $auditoria,
        private CalculadoraSla $sla,
    ) {}

    /**
     * Matriz de transiciones: origen => [destino => roles autorizados].
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function matriz(): array
    {
        $gestion = [R::Gestor->value, R::Administrador->value];
        $devolucion = [R::Gestor->value, R::Coordinador->value, R::Administrador->value];
        $coordinacion = [R::Coordinador->value, R::Administrador->value];
        $tecnico = [R::Tecnico->value, R::Administrador->value];
        $autorizacion = [R::Autorizador->value, R::Coordinador->value, R::Administrador->value];
        $verificacion = [R::Verificador->value, R::Administrador->value];
        $reportante = [R::Reportante->value, R::Administrador->value];
        $suspension = [R::Coordinador->value, R::Tecnico->value, R::Administrador->value];

        return [
            E::Registrada->value => [
                E::EnRevision->value => $gestion,
                E::Cerrada->value => $gestion,
                E::Cancelada->value => $reportante,
            ],
            E::PendienteInformacion->value => [
                E::EnRevision->value => $reportante,
                E::Cancelada->value => [...$reportante, R::Gestor->value, self::SISTEMA],
            ],
            E::EnRevision->value => [
                E::PendienteInformacion->value => $devolucion,
                E::EnClasificacion->value => $gestion,
                E::NoProcedente->value => $gestion,
                E::Cerrada->value => $gestion,
                E::Cancelada->value => $reportante,
            ],
            E::EnClasificacion->value => [
                E::PendienteAsignacion->value => $gestion,
                E::NoProcedente->value => $gestion,
                E::Cerrada->value => $gestion,
                E::Cancelada->value => $reportante,
            ],
            E::PendienteAsignacion->value => [
                E::PendienteInspeccion->value => $coordinacion,
            ],
            E::PendienteInspeccion->value => [
                E::EnDiagnostico->value => $tecnico,
                E::Suspendida->value => $suspension,
            ],
            E::EnDiagnostico->value => [
                E::PendienteInspeccion->value => $coordinacion,
                E::PendienteAutorizacion->value => $coordinacion,
                E::PendienteRecursos->value => $coordinacion,
                E::Programada->value => $coordinacion,
                E::Cerrada->value => $coordinacion,
                E::Suspendida->value => $suspension,
            ],
            E::PendienteAutorizacion->value => [
                E::Programada->value => $autorizacion,
                E::PendienteRecursos->value => $autorizacion,
                E::EnDiagnostico->value => $autorizacion,
                E::Suspendida->value => $autorizacion,
                E::Cancelada->value => $autorizacion,
            ],
            E::PendienteRecursos->value => [
                E::Programada->value => $coordinacion,
                E::Suspendida->value => $suspension,
                E::Cancelada->value => $coordinacion,
            ],
            E::Programada->value => [
                E::EnAtencion->value => $tecnico,
                E::Suspendida->value => $suspension,
            ],
            E::EnAtencion->value => [
                E::PendienteVerificacion->value => $tecnico,
                E::Suspendida->value => $suspension,
            ],
            E::PendienteVerificacion->value => [
                E::EnCorreccion->value => $verificacion,
                E::PendienteConfirmacion->value => $verificacion,
                E::Cerrada->value => $verificacion,
            ],
            E::EnCorreccion->value => [
                E::Programada->value => $coordinacion,
            ],
            E::PendienteConfirmacion->value => [
                E::Cerrada->value => [...$reportante, R::Coordinador->value],
                E::EnCorreccion->value => $reportante,
            ],
            E::Cerrada->value => [
                E::EnCorreccion->value => $reportante,
                E::PendienteAsignacion->value => $reportante,
            ],
            E::Suspendida->value => [
                E::Programada->value => $coordinacion,
                E::EnAtencion->value => $coordinacion,
                E::Cancelada->value => $coordinacion,
            ],
        ];
    }

    public function permite(E $origen, E $destino, ?string $rol): bool
    {
        $roles = self::matriz()[$origen->value][$destino->value] ?? null;

        return $roles !== null && $rol !== null && in_array($rol, $roles, true);
    }

    /**
     * Destinos alcanzables desde el estado actual con el rol activo.
     *
     * @return list<string>
     */
    public function destinosDisponibles(Incidencia $incidencia): array
    {
        $rol = $this->contextoRol->codigo();

        return collect(self::matriz()[$incidencia->estado_codigo->value] ?? [])
            ->filter(fn (array $roles) => $rol !== null && in_array($rol, $roles, true))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * Ejecuta una transición validada y la registra en la bitácora. Debe invocarse dentro de una transacción.
     * $actuarComo permite que el creador del ticket actúe como Reportante (R01) aunque su rol activo sea otro,
     * pues la autorización en esos casos proviene de la propiedad del ticket.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>|null  $evidencias
     *
     * @throws AuthorizationException
     */
    public function transicionar(
        Incidencia $incidencia,
        E $destino,
        ?User $usuario,
        ?string $motivo = null,
        string $tipoEvento = 'CAMBIO_ESTADO',
        array $datos = [],
        bool $publico = true,
        ?array $evidencias = null,
        ?string $actuarComo = null,
    ): void {
        $origen = $incidencia->estado_codigo;
        $rol = $usuario === null ? self::SISTEMA : ($actuarComo ?? $this->contextoRol->codigo());

        $this->validarAutorizacionPrevia($incidencia, $destino);

        if (! isset(self::matriz()[$origen->value][$destino->value])) {
            if ($destino === E::Cerrada && in_array($origen, [E::EnAtencion, E::PendienteVerificacion, E::EnCorreccion], true)) {
                throw ReglaNegocioException::conflicto(
                    'No se puede cerrar una incidencia con verificaciones técnicas obligatorias pendientes (RN-037).',
                    'RN-037',
                );
            }

            throw new TransicionNoPermitidaException($origen, $destino);
        }

        if (! $this->permite($origen, $destino, $rol)) {
            throw new AuthorizationException("El rol activo {$rol} no está autorizado para la transición {$origen->value} -> {$destino->value} (D07).");
        }

        $this->validarInvariantes($incidencia, $origen, $destino);

        $ahora = now();
        $incidencia->estado_codigo = $destino;
        $incidencia->fecha_ultimo_cambio_estado = $ahora;

        if ($origen === E::Registrada) {
            $this->sla->registrarPrimeraRespuesta($incidencia, $ahora);
        }

        if ($destino->esTerminal()) {
            $incidencia->fecha_cierre = $ahora;
            $incidencia->usuario_cierre_id = $usuario?->id;
            $this->sla->congelar($incidencia, $ahora);
        }

        if ($origen === E::Cerrada) {
            $incidencia->fecha_cierre = null;
            $incidencia->usuario_cierre_id = null;
            $incidencia->resultado_final_cierre = null;
            $incidencia->sla_resolucion_cumplido = null;
        }

        $incidencia->save();

        $this->auditoria->registrar(
            tipoEvento: $tipoEvento,
            categoria: CategoriaEvento::Estado,
            incidencia: $incidencia,
            usuario: $usuario,
            motivo: $motivo,
            estadoAnterior: $origen,
            estadoNuevo: $destino,
            datos: $datos === [] ? null : $datos,
            evidencias: $evidencias,
            publico: $publico,
            rolCodigo: $rol,
        );
    }

    private function validarInvariantes(Incidencia $incidencia, E $origen, E $destino): void
    {
        if (in_array($destino, [E::EnDiagnostico, E::EnAtencion], true) && $incidencia->tecnico_asignado_id === null) {
            throw new ReglaNegocioException(
                'La incidencia debe tener un responsable técnico asignado antes de iniciar la inspección o la intervención (RN-021).',
                'RN-021',
            );
        }

    }

    /**
     * Candado operativo RN-029: ninguna transición hacia E10/E11 mientras exista una autorización obligatoria pendiente.
     */
    public function validarAutorizacionPrevia(Incidencia $incidencia, E $destino): void
    {
        if (in_array($destino, [E::Programada, E::EnAtencion], true) && $this->tieneAutorizacionPendiente($incidencia)) {
            throw ReglaNegocioException::conflicto(
                'La intervención requiere autorización previa aprobada antes de comenzar trabajos (RN-029).',
                'RN-029',
            );
        }
    }

    private function tieneAutorizacionPendiente(Incidencia $incidencia): bool
    {
        if ($incidencia->protocolo_emergencia_activo) {
            return false;
        }

        return $incidencia->solicitudesRecursos()
            ->where('estado_solicitud', EstadoSolicitudRecursos::PendienteAutorizacion->value)
            ->where('es_emergencia', false)
            ->exists();
    }
}
