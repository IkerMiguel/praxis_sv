<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[Table(name: 'materiales_actividad', timestamps: false)]
class MaterialActividad extends Model
{
    protected function casts(): array
    {
        return ['cantidad' => 'float'];
    }
}
