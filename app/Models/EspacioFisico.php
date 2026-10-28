<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['piso_id', 'codigo', 'nombre', 'tipo', 'activo'])]
class EspacioFisico extends Model
{
    use HasFactory;

    protected $table = 'espacios_fisicos';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return BelongsTo<Piso, $this>
     */
    public function piso(): BelongsTo
    {
        return $this->belongsTo(Piso::class);
    }

    /**
     * @param  Builder<EspacioFisico>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
