<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sede_id', 'codigo', 'nombre', 'activo'])]
class Edificio extends Model
{
    use HasFactory;

    protected $table = 'edificios';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return HasMany<Piso, $this>
     */
    public function pisos(): HasMany
    {
        return $this->hasMany(Piso::class);
    }

    /**
     * @param  Builder<Edificio>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
