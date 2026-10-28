<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'notificaciones_plataforma', timestamps: false)]
class NotificacionPlataforma extends Model
{
    protected function casts(): array
    {
        return ['leida' => 'boolean', 'fecha_envio' => 'datetime', 'fecha_lectura' => 'datetime'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }
}
