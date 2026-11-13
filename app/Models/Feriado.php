<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[Table(name: 'feriados', key: 'fecha', keyType: 'string', incrementing: false, timestamps: false)]
class Feriado extends Model
{
    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d'];
    }
}
