<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'categoria_padre_id', 'codigo', 'nombre', 'descripcion', 'exige_evidencia_fotografica',
    'requiere_confirmacion_cierre', 'nivel_riesgo_base', 'especialidad_id', 'activo',
])]
class CategoriaIncidencia extends Model
{
    protected $table = 'categorias_incidencia';

    protected function casts(): array
    {
        return [
            'exige_evidencia_fotografica' => 'boolean',
            'requiere_confirmacion_cierre' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CategoriaIncidencia, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'categoria_padre_id');
    }

    /**
     * @return HasMany<CategoriaIncidencia, $this>
     */
    public function subcategorias(): HasMany
    {
        return $this->hasMany(self::class, 'categoria_padre_id');
    }

    /**
     * @return BelongsTo<Especialidad, $this>
     */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }

    /**
     * @param  Builder<CategoriaIncidencia>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * @param  Builder<CategoriaIncidencia>  $query
     */
    public function scopePrincipales(Builder $query): void
    {
        $query->whereNull('categoria_padre_id');
    }
}
