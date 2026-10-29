<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo persistido de los estados de D07 (fuente: App\Enums\EstadoIncidencia).
 */
#[Unguarded]
#[Table(name: 'estados_incidencia', key: 'codigo', keyType: 'string', incrementing: false, timestamps: false)]
class Estado extends Model
{
    protected function casts(): array
    {
        return [
            'requiere_accion_reportante' => 'boolean',
            'es_terminal' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
