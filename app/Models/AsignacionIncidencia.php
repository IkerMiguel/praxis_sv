<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'asignaciones_incidencia', timestamps: false)]
class AsignacionIncidencia extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'es_vigente' => 'boolean', 'excepcion_wip' => 'boolean', 'fecha_visita_tentativa' => 'datetime', 'fecha_fin_vigencia' => 'datetime'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_asignado_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por_id');
    }
}
