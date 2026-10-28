<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[Table(name: 'detalles_solicitud_recursos')]
class DetalleSolicitudRecursos extends Model
{
    protected function casts(): array
    {
        return ['cantidad' => 'float', 'costo_estimado' => 'float', 'disponible' => 'boolean'];
    }
}
