<?php

namespace App\Models;

use App\Enums\CategoriaEvento;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Evento de auditoría append-only (D10). No admite actualizaciones ni borrados.
 */
#[Unguarded]
#[Table(name: 'eventos_auditoria', timestamps: false)]
class EventoAuditoria extends Model
{
    use HasUuids;

    public const PUBLICO = 'PUBLICO';

    public const INTERNO = 'INTERNO';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de auditoría son inmutables (D10).'));
        static::deleting(fn () => throw new LogicException('Los eventos de auditoría no se pueden eliminar (D10).'));
    }

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'evidencias' => 'array',
            'datos' => 'array',
            'categoria' => CategoriaEvento::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }
}
