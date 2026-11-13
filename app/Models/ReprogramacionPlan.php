<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[Table(name: 'reprogramaciones_plan', timestamps: false)]
class ReprogramacionPlan extends Model
{
    protected function casts(): array
    {
        return ['fecha_anterior' => 'date:Y-m-d', 'fecha_nueva' => 'date:Y-m-d', 'created_at' => 'datetime'];
    }
}
