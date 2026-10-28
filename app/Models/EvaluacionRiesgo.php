<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'evaluaciones_riesgo', timestamps: false)]
class EvaluacionRiesgo extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'tiene_riesgo_inmediato' => 'boolean', 'protocolo_activado' => 'boolean'];
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
    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }
}
