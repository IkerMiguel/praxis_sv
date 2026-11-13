<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'duplicados_incidencia', timestamps: false)]
class DuplicadoIncidencia extends Model
{
    protected function casts(): array
    {
        return ['fecha_vinculacion' => 'datetime'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class, 'incidencia_principal_id');
    }
}
