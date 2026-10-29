<?php

namespace App\Http\Resources;

use App\Models\NotificacionPlataforma;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificacionPlataforma
 */
class NotificacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'leida' => $this->leida,
            'incidencia' => $this->whenLoaded('incidencia', fn () => $this->incidencia?->only(['id', 'codigo_ticket'])),
            'fecha_envio' => $this->fecha_envio?->toIso8601String(),
            'fecha_lectura' => $this->fecha_lectura?->toIso8601String(),
        ];
    }
}
