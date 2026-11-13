<?php

namespace App\Models;

use App\Enums\Rol as RolCodigo;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'telefono', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'intentos_fallidos' => 'integer',
            'ultimo_intento_fallido_en' => 'datetime',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Rol, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_roles', 'user_id', 'rol_id')
            ->withPivot(['asignado_por_id', 'fecha_asignacion'])
            ->orderBy('roles.orden');
    }

    /**
     * @return BelongsToMany<Especialidad, $this>
     */
    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidad::class, 'usuario_especialidades', 'user_id', 'especialidad_id');
    }

    /**
     * @return HasMany<TokenRefresco, $this>
     */
    public function tokensRefresco(): HasMany
    {
        return $this->hasMany(TokenRefresco::class);
    }

    /**
     * @return HasMany<Incidencia, $this>
     */
    public function incidenciasAsignadas(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'tecnico_asignado_id');
    }

    /**
     * Códigos de rol asignados al usuario (R01..R07).
     *
     * @return list<string>
     */
    public function codigosRol(): array
    {
        return $this->roles->pluck('codigo')->values()->all();
    }

    public function tieneRol(RolCodigo|string $rol): bool
    {
        $codigo = $rol instanceof RolCodigo ? $rol->value : $rol;

        return in_array($codigo, $this->codigosRol(), true);
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeConRol(Builder $query, RolCodigo $rol): void
    {
        $query->whereHas('roles', fn (Builder $roles) => $roles->where('codigo', $rol->value));
    }

    /**
     * Revoca todos los accesos vigentes del usuario (tokens de acceso y refresh tokens).
     */
    public function revocarSesiones(): void
    {
        $this->tokens()->delete();
        $this->tokensRefresco()->whereNull('revocado_en')->update(['revocado_en' => now()]);
    }
}
