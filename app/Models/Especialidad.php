<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['codigo', 'nombre', 'activo'])]
class Especialidad extends Model
{
    protected $table = 'especialidades';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function tecnicos(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_especialidades', 'especialidad_id', 'user_id');
    }
}
