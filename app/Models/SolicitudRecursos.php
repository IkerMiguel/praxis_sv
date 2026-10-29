<?php

namespace App\Models;

use App\Enums\EstadoSolicitudRecursos;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
#[Table(name: 'solicitudes_recursos')]
class SolicitudRecursos extends Model
{
    protected function casts(): array
    {
        return ['requiere_autorizacion' => 'boolean', 'es_emergencia' => 'boolean', 'recursos_disponibles' => 'boolean', 'fecha_dictamen' => 'datetime', 'fecha_disponibilidad' => 'datetime', 'estado_solicitud' => EstadoSolicitudRecursos::class];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    /**
     * @return HasMany<DetalleSolicitudRecursos, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleSolicitudRecursos::class, 'solicitud_recurso_id');
    }

    /**
     * @return HasMany<SeguimientoRecursos, $this>
     */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoRecursos::class, 'solicitud_recurso_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizador_id');
    }

    public function costoTotalEstimado(): float
    {
        return (float) $this->detalles->sum(fn (DetalleSolicitudRecursos $detalle) => $detalle->cantidad * $detalle->costo_estimado);
    }
}
