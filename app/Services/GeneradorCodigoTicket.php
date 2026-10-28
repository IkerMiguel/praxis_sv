<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generador atómico del consecutivo TKT-AAAA-###### (RN-001). Bloquea la fila del año con
 * SELECT ... FOR UPDATE, por lo que dos radicaciones concurrentes nunca obtienen el mismo número.
 * Debe ejecutarse dentro de la transacción de radicación.
 */
class GeneradorCodigoTicket
{
    public function siguiente(?int $anio = null): string
    {
        $anio ??= (int) now()->setTimezone(config('praxis.zona_horaria'))->format('Y');

        DB::table('consecutivos_ticket')->insertOrIgnore([
            'anio' => $anio,
            'ultimo_numero' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actual = DB::table('consecutivos_ticket')->where('anio', $anio)->lockForUpdate()->value('ultimo_numero');
        $siguiente = (int) $actual + 1;

        DB::table('consecutivos_ticket')->where('anio', $anio)->update([
            'ultimo_numero' => $siguiente,
            'updated_at' => now(),
        ]);

        return sprintf('TKT-%d-%06d', $anio, $siguiente);
    }
}
