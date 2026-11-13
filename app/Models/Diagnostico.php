<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'diagnosticos')]
class Diagnostico extends Model
{
    protected function casts(): array
    {
        return ['fecha_inicio_inspeccion' => 'datetime', 'fecha_fin_diagnostico' => 'datetime', 'fecha_revision' => 'datetime', 'incidencia_confirmada' => 'boolean', 'requiere_recursos' => 'boolean', 'requiere_autorizacion' => 'boolean'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    public const EN_INSPECCION = 'EN_INSPECCION';

    public const REGISTRADO = 'REGISTRADO';

    public const DEVUELTO = 'DEVUELTO';

    public const APROBADO = 'APROBADO';

    /**
     * @return BelongsTo<User, $this>
     */
    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }
}
