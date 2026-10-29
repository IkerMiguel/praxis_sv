<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\Rol as RolCodigo;
use App\Exceptions\ReglaNegocioException;
use App\Models\Especialidad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Directorio de usuarios y asignación de roles (HU-1.2): multi-rol, inactivación lógica con revocación
 * de sesiones e invariante de al menos un administrador (R07) activo.
 */
class AdministracionUsuariosService
{
    public const MENSAJE_ULTIMO_ADMIN = 'Debe existir al menos un administrador activo en la plataforma.';

    public function __construct(private AuditoriaService $auditoria) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $administrador): User
    {
        return DB::transaction(function () use ($datos, $administrador): User {
            $usuario = User::create([
                'name' => $datos['nombre'],
                'email' => Str::lower($datos['email']),
                'telefono' => $datos['telefono'] ?? null,
                'password' => $datos['password'] ?? Str::password(16),
                'activo' => true,
            ]);

            $this->sincronizarRoles($usuario, $datos['roles'], $administrador);
            $this->sincronizarEspecialidades($usuario, $datos['especialidades'] ?? []);

            $this->auditar('USUARIO_CREADO', $usuario, $administrador, $datos['motivo'] ?? 'Alta de usuario.', ['roles' => $datos['roles'], 'especialidades' => $datos['especialidades'] ?? []]);

            return $usuario;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(User $usuario, array $datos, User $administrador): User
    {
        return DB::transaction(function () use ($usuario, $datos, $administrador): User {
            $anterior = $usuario->only(['name', 'email', 'telefono']);
            $usuario->fill([
                'name' => $datos['nombre'] ?? $usuario->name,
                'email' => isset($datos['email']) ? Str::lower($datos['email']) : $usuario->email,
                'telefono' => array_key_exists('telefono', $datos) ? $datos['telefono'] : $usuario->telefono,
            ])->save();

            if (array_key_exists('especialidades', $datos)) {
                $this->sincronizarEspecialidades($usuario, $datos['especialidades'] ?? []);
            }

            $this->auditar('USUARIO_ACTUALIZADO', $usuario, $administrador, $datos['motivo'] ?? 'Actualización de datos.', [
                'anterior' => $anterior,
                'nuevo' => $usuario->only(['name', 'email', 'telefono']),
                'especialidades' => $datos['especialidades'] ?? null,
            ]);

            return $usuario;
        });
    }

    /**
     * @param  list<string>  $roles
     */
    public function actualizarRoles(User $usuario, array $roles, User $administrador, ?string $motivo): User
    {
        return DB::transaction(function () use ($usuario, $roles, $administrador, $motivo): User {
            $anteriores = $usuario->codigosRol();

            if (in_array(RolCodigo::Administrador->value, $anteriores, true) && ! in_array(RolCodigo::Administrador->value, $roles, true)) {
                $this->exigirOtroAdministrador($usuario);
            }

            $this->sincronizarRoles($usuario, $roles, $administrador);
            $usuario->load('roles');

            $this->auditar('ROLES_ACTUALIZADOS', $usuario, $administrador, $motivo ?? 'Cambio de roles.', ['anteriores' => $anteriores, 'nuevos' => $roles]);

            return $usuario;
        });
    }

    public function cambiarEstado(User $usuario, bool $activo, User $administrador, ?string $motivo): User
    {
        return DB::transaction(function () use ($usuario, $activo, $administrador, $motivo): User {
            if (! $activo && $usuario->activo && $usuario->tieneRol(RolCodigo::Administrador)) {
                $this->exigirOtroAdministrador($usuario);
            }

            $usuario->forceFill(['activo' => $activo])->save();

            if (! $activo) {
                $usuario->revocarSesiones();
            }

            $this->auditar($activo ? 'USUARIO_ACTIVADO' : 'USUARIO_INACTIVADO', $usuario, $administrador, $motivo ?? ($activo ? 'Activación.' : 'Inactivación.'));

            return $usuario;
        });
    }

    private function exigirOtroAdministrador(User $usuario): void
    {
        $otros = User::query()
            ->activos()
            ->conRol(RolCodigo::Administrador)
            ->whereKeyNot($usuario->id)
            ->count();

        if ($otros === 0) {
            throw ReglaNegocioException::conflicto(self::MENSAJE_ULTIMO_ADMIN, 'HU-1.2');
        }
    }

    /**
     * @param  list<string>  $codigos
     */
    private function sincronizarRoles(User $usuario, array $codigos, User $administrador): void
    {
        $ids = Rol::query()->whereIn('codigo', $codigos)->pluck('id');
        $actuales = $usuario->roles()->pluck('roles.id');

        $usuario->roles()->detach($actuales->diff($ids)->all());
        $usuario->roles()->attach($ids->diff($actuales)->mapWithKeys(fn (int $id) => [$id => [
            'asignado_por_id' => $administrador->id,
            'fecha_asignacion' => now(),
        ]])->all());
    }

    /**
     * @param  list<string>  $codigos
     */
    private function sincronizarEspecialidades(User $usuario, array $codigos): void
    {
        $usuario->especialidades()->sync(Especialidad::query()->whereIn('codigo', $codigos)->pluck('id')->all());
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function auditar(string $tipo, User $usuario, User $administrador, string $motivo, array $datos = []): void
    {
        $this->auditoria->registrar(
            tipoEvento: $tipo,
            categoria: CategoriaEvento::Configuracion,
            usuario: $administrador,
            motivo: $motivo,
            datos: $datos + ['usuario_afectado' => ['id' => $usuario->id, 'email' => $usuario->email]],
            entidadTipo: 'usuario',
            entidadId: $usuario->id,
        );
    }
}
