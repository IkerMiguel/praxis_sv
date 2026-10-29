<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'historial_cambios_prioridad', timestamps: false)]
class HistorialCambioPrioridad extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    /**
     * @return BelongsTo<Prioridad, $this>
     */
    public function prioridadAnterior(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'prioridad_anterior_id');
    }

    /**
     * @return BelongsTo<Prioridad, $this>
     */
    public function prioridadNueva(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'prioridad_nueva_id');
    }
}
