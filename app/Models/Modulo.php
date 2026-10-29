<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Unguarded]
#[Table(name: 'modulos', timestamps: false)]
class Modulo extends Model
{
    protected function casts(): array
    {
        return ['en_menu' => 'boolean', 'orden' => 'integer'];
    }

    /**
     * @return BelongsToMany<Rol, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_modulo_permisos', 'modulo_id', 'rol_id')->withPivot('nivel_acceso');
    }
}
