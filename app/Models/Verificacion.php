<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'verificaciones', timestamps: false)]
class Verificacion extends Model
{
    protected function casts(): array
    {
        return ['resultado_conforme' => 'boolean', 'criterios_evaluados' => 'array', 'created_at' => 'datetime'];
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
    public function verificador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificador_id');
    }
}
