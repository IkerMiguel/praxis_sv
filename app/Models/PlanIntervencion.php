<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
#[Table(name: 'planes_intervencion')]
class PlanIntervencion extends Model
{
    protected function casts(): array
    {
        return ['fecha_programada' => 'date:Y-m-d'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    public const VIGENTE = 'VIGENTE';

    public const EJECUTADO = 'EJECUTADO';

    public const REEMPLAZADO = 'REEMPLAZADO';

    /**
     * @return HasMany<ReprogramacionPlan, $this>
     */
    public function reprogramaciones(): HasMany
    {
        return $this->hasMany(ReprogramacionPlan::class);
    }
}
