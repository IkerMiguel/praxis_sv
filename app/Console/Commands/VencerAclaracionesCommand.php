<?php

namespace App\Console\Commands;

use App\Enums\EstadoIncidencia;
use App\Models\SolicitudAclaracion;
use App\Services\MaquinaEstados;
use App\Services\Notificador;
use App\Support\ResultadoCierre;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RN-010: cancela (E02 -> E17) las solicitudes sin respuesta tras el plazo institucional de aclaración.
 */
#[Signature('praxis:vencer-aclaraciones')]
#[Description('Cancela las incidencias en Pendiente de información cuyo plazo de aclaración venció (RN-010)')]
class VencerAclaracionesCommand extends Command
{
    public function handle(MaquinaEstados $maquina, Notificador $notificador): int
    {
        $vencidas = 0;

        SolicitudAclaracion::query()
            ->where('estado', SolicitudAclaracion::PENDIENTE)
            ->where('fecha_limite_respuesta', '<', now())
            ->whereHas('incidencia', fn ($query) => $query->where('estado_codigo', EstadoIncidencia::PendienteInformacion->value))
            ->with('incidencia')
            ->chunkById(100, function ($aclaraciones) use ($maquina, $notificador, &$vencidas): void {
                foreach ($aclaraciones as $aclaracion) {
                    DB::transaction(function () use ($aclaracion, $maquina, $notificador): void {
                        $incidencia = $aclaracion->incidencia;
                        $motivo = 'Cancelada por falta de respuesta del reportante dentro del plazo institucional (RN-010).';

                        $aclaracion->forceFill(['estado' => SolicitudAclaracion::VENCIDA])->save();
                        $incidencia->motivo_cancelacion = $motivo;
                        $incidencia->resultado_final_cierre = ResultadoCierre::CANCELADA_POR_FALTA_DE_RESPUESTA;
                        $incidencia->notas_cierre = $motivo;

                        $maquina->transicionar($incidencia, EstadoIncidencia::Cancelada, null, $motivo, 'CADUCIDAD_ACLARACION', ['solicitud_aclaracion_id' => $aclaracion->id]);
                        $notificador->alReportante($incidencia, 'CANCELACION', "Tu solicitud {$incidencia->codigo_ticket} fue cancelada", $motivo);
                    });

                    $vencidas++;
                }
            });

        $this->info("Solicitudes canceladas por vencimiento de aclaración: {$vencidas}");

        return self::SUCCESS;
    }
}
