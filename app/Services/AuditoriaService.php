<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\EstadoIncidencia;
use App\Models\EventoAuditoria;
use App\Models\Incidencia;
use App\Models\User;
use App\Support\ContextoRol;
use Illuminate\Http\Request;

/**
 * Registra eventos en la bitácora append-only (D10). Se invoca dentro de la misma transacción
 * que la operación auditada, de modo que el evento y el cambio se confirman o revierten juntos.
 */
class AuditoriaService
{
    public function __construct(private ContextoRol $contextoRol, private Request $request) {}

    /**
     * @param  array<string, mixed>|null  $datos
     * @param  list<array<string, mixed>>|null  $evidencias
     */
    public function registrar(
        string $tipoEvento,
        CategoriaEvento $categoria,
        ?Incidencia $incidencia = null,
        ?User $usuario = null,
        ?string $motivo = null,
        ?EstadoIncidencia $estadoAnterior = null,
        ?EstadoIncidencia $estadoNuevo = null,
        ?array $datos = null,
        ?array $evidencias = null,
        bool $publico = false,
        ?string $entidadTipo = null,
        string|int|null $entidadId = null,
        ?string $rolCodigo = null,
    ): EventoAuditoria {
        return EventoAuditoria::create([
            'incidencia_id' => $incidencia?->id,
            'tipo_evento' => $tipoEvento,
            'categoria' => $categoria,
            'visibilidad' => $publico ? EventoAuditoria::PUBLICO : EventoAuditoria::INTERNO,
            'fecha_hora' => now(),
            'usuario_id' => $usuario?->id,
            'rol_codigo' => $rolCodigo ?? ($usuario ? $this->contextoRol->codigo() : 'SISTEMA'),
            'estado_anterior' => $estadoAnterior?->value,
            'estado_nuevo' => $estadoNuevo?->value,
            'motivo' => $motivo,
            'evidencias' => $evidencias,
            'datos' => $datos,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId === null ? null : (string) $entidadId,
            'ip' => $this->request->ip(),
        ]);
    }
}
