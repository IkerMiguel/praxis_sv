<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['edificio_id', 'codigo', 'nombre', 'nivel', 'activo'])]
class Piso extends Model
{
    use HasFactory;

    protected $table = 'pisos';

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'nivel' => 'integer'];
    }

    /**
     * @return BelongsTo<Edificio, $this>
     */
    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    /**
     * @return HasMany<EspacioFisico, $this>
     */
    public function espacios(): HasMany
    {
        return $this->hasMany(EspacioFisico::class);
    }

    /**
     * @param  Builder<Piso>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
