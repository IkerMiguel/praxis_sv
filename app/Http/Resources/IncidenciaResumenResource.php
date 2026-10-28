<?php

namespace App\Http\Resources;

use App\Models\Incidencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de listado (mis solicitudes, bandejas, consola).
 *
 * @mixin Incidencia
 */
class IncidenciaResumenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_ticket' => $this->codigo_ticket,
            'resumen' => $this->resumen,
            'estado' => [
                'codigo' => $this->estado_codigo->value,
                'nombre' => $this->estado_codigo->nombre(),
                'color' => $this->whenLoaded('estado', fn () => $this->estado->color),
                'requiere_accion_reportante' => $this->whenLoaded('estado', fn () => $this->estado->requiere_accion_reportante),
            ],
            'prioridad' => $this->whenLoaded('prioridad', fn () => $this->prioridad?->only(['id', 'codigo', 'nombre', 'color'])),
            'categoria' => $this->whenLoaded('categoria', fn () => $this->categoria->only(['id', 'codigo', 'nombre'])),
            'ubicacion' => $this->when($this->relationLoaded('espacioFisico'), fn () => UbicacionIncidencia::de($this->resource)),
            'tecnico_asignado' => $this->whenLoaded('tecnicoAsignado', fn () => $this->tecnicoAsignado?->only(['id', 'name'])),
            'es_riesgo_inmediato' => $this->es_riesgo_inmediato,
            'posible_duplicado' => $this->posible_duplicado,
            'fecha_registro' => $this->created_at?->toIso8601String(),
            'fecha_actualizacion' => $this->updated_at?->toIso8601String(),
            'fecha_cierre' => $this->fecha_cierre?->toIso8601String(),
        ];
    }
}
