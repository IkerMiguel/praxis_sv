<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['codigo', 'nombre', 'descripcion', 'ruta_inicio', 'orden'])]
class Rol extends Model
{
    protected $table = 'roles';

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_roles', 'rol_id', 'user_id');
    }

    /**
     * @return BelongsToMany<Modulo, $this>
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'rol_modulo_permisos', 'rol_id', 'modulo_id')->withPivot('nivel_acceso');
    }
}
