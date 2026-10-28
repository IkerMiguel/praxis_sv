<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\CodigoPrioridad;
use App\Enums\EstadoIncidencia;
use App\Enums\EtapaEvidencia;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CategoriaIncidencia;
use App\Models\EspacioFisico;
use App\Models\HistorialCambioPrioridad;
use App\Models\Incidencia;
use App\Models\User;
use App\Support\Parametro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Radicación de incidencias (HU-3.1, HU-3.2, HU-3.3): generación atómica del consecutivo, estado inicial E01,
 * evidencias condicionales por categoría (RN-005), marca de posible duplicado y protocolo de riesgo (RN-016/017).
 */
class RadicacionService
{
    public function __construct(
        private GeneradorCodigoTicket $generador,
        private EvidenciaService $evidencias,
        private AuditoriaService $auditoria,
        private Notificador $notificador,
        private ParametrosService $parametros,
        private CatalogoService $catalogo,
        private CalculadoraSla $sla,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function radicar(array $datos, User $usuario, ?string $claveIdempotencia): Incidencia
    {
        if ($claveIdempotencia !== null) {
            $existente = Incidencia::query()
                ->where('usuario_creador_id', $usuario->id)
                ->where('clave_idempotencia', $claveIdempotencia)
                ->first();

            if ($existente !== null) {
                return $existente;
            }
        }

        $espacio = EspacioFisico::query()->with('piso.edificio.sede')->findOrFail($datos['espacio_fisico_id']);
        $this->validarJerarquia($espacio, $datos);

        $categoria = CategoriaIncidencia::query()->findOrFail($datos['categoria_id']);
        $subcategoria = isset($datos['subcategoria_id']) ? CategoriaIncidencia::query()->find($datos['subcategoria_id']) : null;
        $idsEvidencias = array_values(array_map('intval', $datos['evidencias'] ?? []));
        $exigeEvidencia = $categoria->exige_evidencia_fotografica || ($subcategoria?->exige_evidencia_fotografica ?? false);

        if ($exigeEvidencia && $idsEvidencias === []) {
            throw ValidationException::withMessages([
                'evidencias' => 'Para categorías de daño estructural es obligatorio adjuntar al menos una evidencia fotográfica (RN-005)',
            ]);
        }

        $maximo = $this->parametros->entero(Parametro::MAX_EVIDENCIAS_POR_REPORTE);

        if (count($idsEvidencias) > $maximo) {
            throw ValidationException::withMessages([
                'evidencias' => "Se ha alcanzado el límite máximo permitido de {$maximo} archivos por reporte",
            ]);
        }

        $esRiesgo = (bool) ($datos['es_riesgo_inmediato'] ?? false);
        $coincidencias = $this->coincidenciasActivas($espacio->id, $categoria->id)->count();

        try {
            $incidencia = DB::transaction(function () use ($datos, $usuario, $espacio, $categoria, $subcategoria, $idsEvidencias, $esRiesgo, $coincidencias, $claveIdempotencia): Incidencia {
                $incidencia = new Incidencia([
                    'codigo_ticket' => $this->generador->siguiente(),
                    'resumen' => $datos['resumen'] ?? Str::limit(trim((string) $datos['descripcion']), 80),
                    'descripcion' => $datos['descripcion'],
                    'canal_radicacion' => $datos['canal_radicacion'] ?? 'WEB',
                    'sede_id' => $espacio->piso->edificio->sede_id,
                    'edificio_id' => $espacio->piso->edificio_id,
                    'piso_id' => $espacio->piso_id,
                    'espacio_fisico_id' => $espacio->id,
                    'categoria_id' => $categoria->id,
                    'subcategoria_id' => $subcategoria?->id,
                    'estado_codigo' => EstadoIncidencia::Registrada,
                    'usuario_creador_id' => $usuario->id,
                    'clave_idempotencia' => $claveIdempotencia,
                    'es_riesgo_inmediato' => $esRiesgo,
                    'descripcion_riesgo' => $esRiesgo ? $datos['descripcion_riesgo'] : null,
                    'posible_duplicado' => $coincidencias > 0,
                    'fecha_ultimo_cambio_estado' => now(),
                ]);

                $prioridadInicial = $this->catalogo->prioridadPorCodigo($esRiesgo ? CodigoPrioridad::Critica->value : CodigoPrioridad::Media->value);
                $incidencia->prioridad_id = $esRiesgo ? $prioridadInicial?->id : null;
                $incidencia->created_at = now();
                $incidencia->setRelation('prioridad', $prioridadInicial);
                $this->sla->fijarMetas($incidencia);

                if (! $esRiesgo) {
                    $incidencia->fecha_limite_resolucion = null;
                }

                $incidencia->save();

                $evidencias = $this->evidencias->vincularPendientes($idsEvidencias, $usuario, $incidencia, EtapaEvidencia::Reporte);

                $this->auditoria->registrar(
                    tipoEvento: 'CREACION',
                    categoria: CategoriaEvento::Estado,
                    incidencia: $incidencia,
                    usuario: $usuario,
                    motivo: 'Incidencia radicada por el reportante.',
                    estadoNuevo: EstadoIncidencia::Registrada,
                    datos: [
                        'codigo_ticket' => $incidencia->codigo_ticket,
                        'canal' => $incidencia->canal_radicacion,
                        'posible_duplicado' => $incidencia->posible_duplicado,
                        'es_problema_diferente' => (bool) ($datos['es_problema_diferente'] ?? false),
                    ],
                    evidencias: $this->evidencias->paraAuditoria($evidencias),
                    publico: true,
                    rolCodigo: Rol::Reportante->value,
                );

                if ($esRiesgo) {
                    $this->activarAlertaRiesgo($incidencia, $usuario);
                }

                return $incidencia;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($claveIdempotencia === null) {
                throw $exception;
            }

            return Incidencia::query()
                ->where('usuario_creador_id', $usuario->id)
                ->where('clave_idempotencia', $claveIdempotencia)
                ->firstOrFail();
        }

        return $incidencia;
    }

    /**
     * Incidencias activas (E01-E11) en el mismo espacio durante la ventana parametrizada (RN-011).
     *
     * @return Collection<int, Incidencia>
     */
    public function coincidenciasActivas(int $espacioFisicoId, ?int $categoriaId = null, ?string $excluirId = null): Collection
    {
        $dias = $this->parametros->entero(Parametro::VENTANA_DUPLICADOS_DIAS);

        return Incidencia::query()
            ->where('espacio_fisico_id', $espacioFisicoId)
            ->when($categoriaId !== null, fn (Builder $query) => $query->where('categoria_id', $categoriaId))
            ->when($excluirId !== null, fn (Builder $query) => $query->whereKeyNot($excluirId))
            ->enEstados(EstadoIncidencia::activosParaDuplicados())
            ->where('created_at', '>=', now()->subDays($dias))
            ->with(['categoria', 'estado'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Suscribe al usuario al seguimiento de una incidencia existente (HU-3.3 escenario 2).
     */
    public function suscribir(Incidencia $incidencia, User $usuario): void
    {
        if ($incidencia->estado_codigo->esTerminal()) {
            throw ReglaNegocioException::conflicto('Solo es posible seguir incidencias en curso.');
        }

        if ($incidencia->esCreadaPor($usuario)) {
            throw ReglaNegocioException::conflicto('Usted es el creador de esta incidencia y ya recibe sus actualizaciones.');
        }

        DB::transaction(function () use ($incidencia, $usuario): void {
            $incidencia->seguidores()->syncWithoutDetaching([$usuario->id => ['fecha_suscripcion' => now()]]);

            $this->auditoria->registrar(
                tipoEvento: 'SUSCRIPCION_SEGUIMIENTO',
                categoria: CategoriaEvento::Actividad,
                incidencia: $incidencia,
                usuario: $usuario,
                motivo: 'Usuario adherido al seguimiento del reporte existente.',
                rolCodigo: Rol::Reportante->value,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarJerarquia(EspacioFisico $espacio, array $datos): void
    {
        $piso = $espacio->piso;
        $edificio = $piso->edificio;

        $inconsistente = (isset($datos['piso_id']) && (int) $datos['piso_id'] !== $piso->id)
            || (isset($datos['edificio_id']) && (int) $datos['edificio_id'] !== $edificio->id)
            || (isset($datos['sede_id']) && (int) $datos['sede_id'] !== $edificio->sede_id);

        if ($inconsistente) {
            throw ValidationException::withMessages([
                'espacio_fisico_id' => 'El espacio seleccionado no pertenece a la sede, edificio y piso indicados (RN-003).',
            ]);
        }

        if (! $espacio->activo || ! $piso->activo || ! $edificio->activo || ! $edificio->sede->activo) {
            throw ValidationException::withMessages([
                'espacio_fisico_id' => 'La ubicación seleccionada se encuentra inactiva.',
            ]);
        }
    }

    /**
     * Riesgo declarado por el reportante: prioridad crítica automática, alerta a coordinación y gestión (RN-017)
     * y registro del evento urgente (RN-018).
     */
    private function activarAlertaRiesgo(Incidencia $incidencia, User $usuario): void
    {
        HistorialCambioPrioridad::create([
            'incidencia_id' => $incidencia->id,
            'prioridad_anterior_id' => null,
            'prioridad_nueva_id' => $incidencia->prioridad_id,
            'origen' => 'RIESGO_DECLARADO',
            'usuario_id' => $usuario->id,
            'justificacion' => 'Riesgo inmediato declarado al radicar: '.$incidencia->descripcion_riesgo,
            'created_at' => now(),
        ]);

        $this->auditoria->registrar(
            tipoEvento: 'RIESGO_INMEDIATO_DECLARADO',
            categoria: CategoriaEvento::Forense,
            incidencia: $incidencia,
            usuario: $usuario,
            motivo: $incidencia->descripcion_riesgo,
            datos: ['prioridad' => CodigoPrioridad::Critica->value],
            rolCodigo: Rol::Reportante->value,
        );

        $this->notificador->aRoles(
            $incidencia,
            [Rol::Coordinador, Rol::Gestor],
            'RIESGO_INMEDIATO',
            "Riesgo inmediato reportado en {$incidencia->codigo_ticket}",
            "Se declaró un peligro inminente: {$incidencia->descripcion_riesgo}. Atención prioritaria requerida.",
        );
    }
}
