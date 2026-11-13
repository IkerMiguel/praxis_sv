<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['codigo', 'nombre', 'nivel', 'horas_primera_respuesta', 'horas_resolucion', 'color'])]
class Prioridad extends Model
{
    protected $table = 'prioridades';

    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'horas_primera_respuesta' => 'integer',
            'horas_resolucion' => 'integer',
        ];
    }
}
