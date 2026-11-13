<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Enums\Rol;
use App\Models\Incidencia;
use App\Models\NotificacionPlataforma;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * NotificationDispatcher (HU-2.5): genera avisos internos en los hitos de comunicación de la matriz RACI
 * y deja constancia de cada comunicación en la trazabilidad de la incidencia (D10 Comunicaciones).
 */
class Notificador
{
    public function __construct(private AuditoriaService $auditoria) {}

    /**
     * Notifica al reportante y a los usuarios suscritos al seguimiento de la incidencia.
     */
    public function alReportante(Incidencia $incidencia, string $tipo, string $titulo, string $mensaje): void
    {
        $destinatarios = collect([$incidencia->creador])
            ->merge($incidencia->seguidores)
            ->filter()
            ->unique('id');

        $this->enviar($incidencia, $destinatarios, $tipo, $titulo, $mensaje, publico: true);
    }

    /**
     * Notifica a todos los usuarios activos con alguno de los roles indicados.
     */
    public function aRoles(Incidencia $incidencia, array $roles, string $tipo, string $titulo, string $mensaje): void
    {
        $destinatarios = User::query()
            ->activos()
            ->whereHas('roles', fn ($query) => $query->whereIn('codigo', array_map(fn (Rol $rol) => $rol->value, $roles)))
            ->get();

        $this->enviar($incidencia, $destinatarios, $tipo, $titulo, $mensaje);
    }

    public function aUsuario(Incidencia $incidencia, ?User $usuario, string $tipo, string $titulo, string $mensaje): void
    {
        if ($usuario === null) {
            return;
        }

        $this->enviar($incidencia, collect([$usuario]), $tipo, $titulo, $mensaje);
    }

    /**
     * @param  Collection<int, User>  $destinatarios
     */
    private function enviar(Incidencia $incidencia, Collection $destinatarios, string $tipo, string $titulo, string $mensaje, bool $publico = false): void
    {
        if ($destinatarios->isEmpty()) {
            return;
        }

        $ahora = now();

        NotificacionPlataforma::insert($destinatarios->map(fn (User $usuario) => [
            'incidencia_id' => $incidencia->id,
            'usuario_destino_id' => $usuario->id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'leida' => false,
            'fecha_envio' => $ahora,
        ])->values()->all());

        $this->auditoria->registrar(
            tipoEvento: 'COMUNICACION',
            categoria: CategoriaEvento::Comunicacion,
            incidencia: $incidencia,
            motivo: $mensaje,
            datos: [
                'tipo_notificacion' => $tipo,
                'titulo' => $titulo,
                'destinatarios' => $destinatarios->map(fn (User $usuario) => ['id' => $usuario->id, 'nombre' => $usuario->name])->values()->all(),
            ],
            publico: $publico,
            rolCodigo: 'SISTEMA',
        );
    }
}
