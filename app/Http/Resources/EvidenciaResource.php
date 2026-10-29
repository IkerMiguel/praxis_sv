<?php

namespace App\Http\Resources;

use App\Models\Evidencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Evidencia
 */
class EvidenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'etapa' => $this->etapa,
            'nombre_original' => $this->nombre_original,
            'mime_type' => $this->mime_type,
            'tamano_bytes' => $this->tamano_bytes,
            'hash_sha256' => $this->hash_sha256,
            'usuario_carga' => $this->whenLoaded('usuarioCarga', fn () => $this->usuarioCarga->only(['id', 'name'])),
            'fecha_carga' => $this->created_at?->toIso8601String(),
            'url' => URL::temporarySignedRoute(
                'evidencias.descargar',
                now()->addMinutes((int) config('praxis.evidencias_url_minutos')),
                ['evidencia' => $this->id],
            ),
        ];
    }
}
