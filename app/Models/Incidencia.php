<?php

namespace App\Models;

use App\Enums\EstadoIncidencia;
use Database\Factories\IncidenciaFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Entidad central del proceso (D14). Su estado solo cambia a través de App\Services\MaquinaEstados.
 *
 * @property EstadoIncidencia $estado_codigo
 */
#[Unguarded]
class Incidencia extends Model
{
    /** @use HasFactory<IncidenciaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'incidencias';

    protected function casts(): array
    {
        return [
            'estado_codigo' => EstadoIncidencia::class,
            'es_riesgo_inmediato' => 'boolean',
            'protocolo_emergencia_activo' => 'boolean',
            'posible_duplicado' => 'boolean',
            'es_cierre_administrativo' => 'boolean',
            'sla_respuesta_cumplido' => 'boolean',
            'sla_resolucion_cumplido' => 'boolean',
            'numero_reaperturas' => 'integer',
            'numero_reprocesos' => 'integer',
            'calificacion_servicio' => 'integer',
            'fecha_ultima_reapertura' => 'datetime',
            'fecha_confirmacion' => 'datetime',
            'fecha_cierre' => 'datetime',
            'fecha_primera_respuesta' => 'datetime',
            'fecha_limite_respuesta' => 'datetime',
            'fecha_limite_resolucion' => 'datetime',
            'fecha_ultimo_cambio_estado' => 'datetime',
        ];
    }

    public function estaEn(EstadoIncidencia ...$estados): bool
    {
        return in_array($this->estado_codigo, $estados, true);
    }

    public function esCreadaPor(User $usuario): bool
    {
        return (int) $this->usuario_creador_id === (int) $usuario->id;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tecnicoAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_asignado_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<Edificio, $this>
     */
    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    /**
     * @return BelongsTo<Piso, $this>
     */
    public function piso(): BelongsTo
    {
        return $this->belongsTo(Piso::class);
    }

    /**
     * @return BelongsTo<EspacioFisico, $this>
     */
    public function espacioFisico(): BelongsTo
    {
        return $this->belongsTo(EspacioFisico::class);
    }

    /**
     * @return BelongsTo<CategoriaIncidencia, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaIncidencia::class);
    }

    /**
     * @return BelongsTo<CategoriaIncidencia, $this>
     */
    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaIncidencia::class, 'subcategoria_id');
    }

    /**
     * @return BelongsTo<Prioridad, $this>
     */
    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class);
    }

    /**
     * @return BelongsTo<Estado, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_codigo', 'codigo');
    }

    /**
     * @return BelongsTo<Incidencia, $this>
     */
    public function incidenciaPrincipal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'incidencia_principal_id');
    }

    /**
     * @return HasMany<Incidencia, $this>
     */
    public function duplicados(): HasMany
    {
        return $this->hasMany(self::class, 'incidencia_principal_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function seguidores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seguidores_incidencia', 'incidencia_id', 'user_id')
            ->withPivot('fecha_suscripcion');
    }

    /**
     * @return HasMany<Evidencia, $this>
     */
    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class);
    }

    /**
     * @return HasMany<SolicitudAclaracion, $this>
     */
    public function solicitudesAclaracion(): HasMany
    {
        return $this->hasMany(SolicitudAclaracion::class);
    }

    /**
     * @return HasOne<SolicitudAclaracion, $this>
     */
    public function aclaracionPendiente(): HasOne
    {
        return $this->hasOne(SolicitudAclaracion::class)->where('estado', SolicitudAclaracion::PENDIENTE)->latestOfMany();
    }

    /**
     * @return HasMany<AsignacionIncidencia, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionIncidencia::class);
    }

    /**
     * @return HasMany<Diagnostico, $this>
     */
    public function diagnosticos(): HasMany
    {
        return $this->hasMany(Diagnostico::class);
    }

    /**
     * @return HasOne<Diagnostico, $this>
     */
    public function diagnosticoActual(): HasOne
    {
        return $this->hasOne(Diagnostico::class)->latestOfMany();
    }

    /**
     * @return HasMany<SolicitudRecursos, $this>
     */
    public function solicitudesRecursos(): HasMany
    {
        return $this->hasMany(SolicitudRecursos::class);
    }

    /**
     * @return HasMany<PlanIntervencion, $this>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(PlanIntervencion::class);
    }

    /**
     * @return HasOne<PlanIntervencion, $this>
     */
    public function planVigente(): HasOne
    {
        return $this->hasOne(PlanIntervencion::class)->where('estado_plan', PlanIntervencion::VIGENTE)->latestOfMany();
    }

    /**
     * @return HasMany<ActividadMantenimiento, $this>
     */
    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadMantenimiento::class);
    }

    /**
     * @return HasOne<ActividadMantenimiento, $this>
     */
    public function actividadActual(): HasOne
    {
        return $this->hasOne(ActividadMantenimiento::class)->latestOfMany();
    }

    /**
     * @return HasMany<Suspension, $this>
     */
    public function suspensiones(): HasMany
    {
        return $this->hasMany(Suspension::class);
    }

    /**
     * @return HasMany<Verificacion, $this>
     */
    public function verificaciones(): HasMany
    {
        return $this->hasMany(Verificacion::class);
    }

    /**
     * @return HasMany<EvaluacionRiesgo, $this>
     */
    public function evaluacionesRiesgo(): HasMany
    {
        return $this->hasMany(EvaluacionRiesgo::class);
    }

    /**
     * @return HasMany<EventoAuditoria, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAuditoria::class);
    }

    /**
     * @param  Builder<Incidencia>  $query
     * @param  list<EstadoIncidencia>  $estados
     */
    public function scopeEnEstados(Builder $query, array $estados): void
    {
        $query->whereIn('estado_codigo', EstadoIncidencia::valores($estados));
    }
}
