<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'evidencias', timestamps: false)]
class Evidencia extends Model
{
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'tamano_bytes' => 'integer'];
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
    public function usuarioCarga(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_carga_id');
    }
}
