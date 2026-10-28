<?php

namespace App\Http\Resources;

use App\Enums\EstadoIncidencia;
use App\Models\EventoAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nodo de la línea de tiempo (D10: ID, incidencia, tipo, fecha, usuario y rol, estados, motivo, evidencias).
 *
 * @mixin EventoAuditoria
 */
class EventoAuditoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incidencia_id' => $this->incidencia_id,
            'tipo_evento' => $this->tipo_evento,
            'categoria' => $this->categoria->value,
            'fecha_hora' => $this->fecha_hora?->toIso8601String(),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'rol' => $this->rol_codigo,
            'estado_anterior' => $this->estadoDe($this->estado_anterior),
            'estado_nuevo' => $this->estadoDe($this->estado_nuevo),
            'motivo' => $this->motivo,
            'datos' => $this->datos,
            'evidencias' => $this->evidencias,
        ];
    }

    /**
     * @return array{codigo: string, nombre: string}|null
     */
    private function estadoDe(?string $codigo): ?array
    {
        $estado = $codigo === null ? null : EstadoIncidencia::tryFrom($codigo);

        return $estado === null ? null : ['codigo' => $estado->value, 'nombre' => $estado->nombre()];
    }
}
