<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
#[Table(name: 'actividades_mantenimiento')]
class ActividadMantenimiento extends Model
{
    protected function casts(): array
    {
        return ['fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime', 'tiempo_invertido_minutos' => 'integer'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    /**
     * @return HasMany<MaterialActividad, $this>
     */
    public function materiales(): HasMany
    {
        return $this->hasMany(MaterialActividad::class, 'actividad_mantenimiento_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tecnicoEjecutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_ejecutor_id');
    }
}
