<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[Table(name: 'solicitudes_aclaracion')]
class SolicitudAclaracion extends Model
{
    protected function casts(): array
    {
        return ['fecha_solicitud' => 'datetime', 'fecha_limite_respuesta' => 'datetime', 'fecha_respuesta' => 'datetime'];
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    public const PENDIENTE = 'PENDIENTE';

    public const RESPONDIDA = 'RESPONDIDA';

    public const VENCIDA = 'VENCIDA';

    /**
     * @return BelongsTo<User, $this>
     */
    public function gestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestor_solicitante_id');
    }
}
