<?php

namespace App\Services;

use App\Enums\CategoriaEvento;
use App\Models\MatrizPrioridad;
use App\Models\ParametroSistema;
use App\Models\Prioridad;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Calibración de reglas del sistema (HU-11.3): matriz ITIL, SLA, WIP, ventanas y plazos. Cada ajuste
 * se audita con valor anterior y nuevo, y se invalida la caché para que aplique de inmediato (hot-reload).
 * Los cambios no son retroactivos: las incidencias ya cerradas conservan sus valores congelados.
 */
class ParametrizacionService
{
    public const MENSAJE_ENTERO_POSITIVO = 'El parámetro debe ser un número entero positivo mayor a cero';

    public const MENSAJES = [
        'parametros.*.integer' => self::MENSAJE_ENTERO_POSITIVO,
        'parametros.*.min' => self::MENSAJE_ENTERO_POSITIVO,
        'parametros.*.required' => self::MENSAJE_ENTERO_POSITIVO,
        'sla.*.horas_primera_respuesta.min' => self::MENSAJE_ENTERO_POSITIVO,
        'sla.*.horas_resolucion.min' => self::MENSAJE_ENTERO_POSITIVO,
        'prioridades.*.horas_primera_respuesta.min' => self::MENSAJE_ENTERO_POSITIVO,
        'prioridades.*.horas_resolucion.min' => self::MENSAJE_ENTERO_POSITIVO,
        'plazo_aclaracion_dias_habiles.min' => self::MENSAJE_ENTERO_POSITIVO,
    ];

    public function __construct(
        private AuditoriaService $auditoria,
        private ParametrosService $parametros,
        private CatalogoService $catalogo,
    ) {}

    /**
     * @param  array{matriz?: list<array{impacto: string, urgencia: string, prioridad: string}>, sla?: list<array{codigo: string, horas_primera_respuesta: int, horas_resolucion: int}>, parametros?: array<string, int|string>}  $cambios
     */
    public function aplicar(array $cambios, User $administrador): void
    {
        DB::transaction(function () use ($cambios, $administrador): void {
            $prioridades = Prioridad::query()->get()->keyBy('codigo');

            foreach ($cambios['matriz'] ?? [] as $celda) {
                $registro = MatrizPrioridad::query()->with('prioridad')->firstOrNew(['impacto' => $celda['impacto'], 'urgencia' => $celda['urgencia']]);
                $anterior = $registro->prioridad?->codigo;

                if ($anterior === $celda['prioridad']) {
                    continue;
                }

                $registro->fill(['prioridad_id' => $prioridades[$celda['prioridad']]->id, 'modificado_por_id' => $administrador->id])->save();
                $this->auditar($administrador, 'MATRIZ_PRIORIDAD_ACTUALIZADA', "MATRIZ:{$celda['impacto']}x{$celda['urgencia']}", $anterior, $celda['prioridad']);
            }

            foreach ($cambios['sla'] ?? [] as $sla) {
                $prioridad = $prioridades[$sla['codigo']];
                $anterior = ['horas_primera_respuesta' => $prioridad->horas_primera_respuesta, 'horas_resolucion' => $prioridad->horas_resolucion];
                $nuevo = ['horas_primera_respuesta' => (int) $sla['horas_primera_respuesta'], 'horas_resolucion' => (int) $sla['horas_resolucion']];

                if ($anterior === $nuevo) {
                    continue;
                }

                $prioridad->fill($nuevo)->save();
                $this->auditar($administrador, 'SLA_ACTUALIZADO', "SLA:{$sla['codigo']}", $anterior, $nuevo);
            }

            foreach ($cambios['parametros'] ?? [] as $clave => $valor) {
                $parametro = ParametroSistema::query()->findOrFail($clave);
                $anterior = $parametro->valor;

                if ((string) $anterior === (string) $valor) {
                    continue;
                }

                $parametro->forceFill(['valor' => (string) $valor, 'modificado_por_id' => $administrador->id])->save();
                $this->auditar($administrador, 'PARAMETRO_ACTUALIZADO', $clave, $anterior, (string) $valor);
            }
        });

        $this->parametros->invalidar();
        $this->catalogo->invalidarMatrizYPrioridades();
    }

    private function auditar(User $administrador, string $tipo, string $clave, mixed $anterior, mixed $nuevo): void
    {
        $this->auditoria->registrar(
            tipoEvento: $tipo,
            categoria: CategoriaEvento::Configuracion,
            usuario: $administrador,
            motivo: "Cambio de configuración en {$clave}.",
            datos: ['parametro' => $clave, 'valor_anterior' => $anterior, 'valor_nuevo' => $nuevo],
            entidadTipo: 'parametro',
            entidadId: $clave,
        );
    }
}
