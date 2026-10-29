<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['impacto', 'urgencia', 'prioridad_id', 'modificado_por_id'])]
class MatrizPrioridad extends Model
{
    protected $table = 'matriz_prioridad';

    /**
     * @return BelongsTo<Prioridad, $this>
     */
    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class);
    }
}
