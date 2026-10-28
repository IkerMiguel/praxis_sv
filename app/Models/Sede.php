<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'direccion', 'activo'])]
class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return HasMany<Edificio, $this>
     */
    public function edificios(): HasMany
    {
        return $this->hasMany(Edificio::class);
    }

    /**
     * @param  Builder<Sede>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
