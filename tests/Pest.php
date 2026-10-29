<?php

use App\Enums\EstadoIncidencia;
use App\Models\ActividadMantenimiento;
use App\Models\Diagnostico;
use App\Models\Evidencia;
use App\Models\Incidencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Las pruebas de Feature usan una base de datos limpia con los catálogos del
| proceso sembrados (ver Tests\TestCase).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers del flujo operativo
|--------------------------------------------------------------------------
*/

/**
 * Incidencia en E07 con un diagnóstico ya registrado por el técnico asignado.
 */
function incidenciaConDiagnostico(User $tecnico, bool $confirmada = true, array $atributos = []): Incidencia
{
    $incidencia = Incidencia::factory()
        ->enEstado(EstadoIncidencia::EnDiagnostico)
        ->asignadaA($tecnico)
        ->categoria('CERRAJERIA')
        ->create($atributos);

    Diagnostico::create([
        'incidencia_id' => $incidencia->id,
        'tecnico_id' => $tecnico->id,
        'fecha_inicio_inspeccion' => now()->subHour(),
        'fecha_fin_diagnostico' => now(),
        'incidencia_confirmada' => $confirmada,
        'hallazgos' => 'Hallazgos registrados en sitio.',
        'descripcion_problema' => 'Desgaste del mecanismo.',
        'intervencion_propuesta' => 'Reemplazo de piezas.',
        'justificacion_no_confirmada' => $confirmada ? null : 'El elemento opera con normalidad.',
        'estado' => Diagnostico::REGISTRADO,
    ]);

    return $incidencia;
}

/**
 * Incidencia en E11 con la intervención iniciada por el técnico.
 */
function incidenciaEnAtencion(User $tecnico, array $atributos = []): Incidencia
{
    $incidencia = Incidencia::factory()
        ->enEstado(EstadoIncidencia::EnAtencion)
        ->asignadaA($tecnico)
        ->categoria('CERRAJERIA')
        ->create($atributos);

    ActividadMantenimiento::create([
        'incidencia_id' => $incidencia->id,
        'tecnico_ejecutor_id' => $tecnico->id,
        'fecha_inicio' => now()->subHour(),
    ]);

    return $incidencia;
}

/**
 * Incidencia en E12 con la intervención registrada y su evidencia de ejecución.
 */
function incidenciaPendienteVerificacion(User $tecnico, string $categoria = 'CERRAJERIA', array $atributos = []): Incidencia
{
    $incidencia = Incidencia::factory()
        ->enEstado(EstadoIncidencia::PendienteVerificacion)
        ->asignadaA($tecnico)
        ->categoria($categoria)
        ->create($atributos);

    $actividad = ActividadMantenimiento::create([
        'incidencia_id' => $incidencia->id,
        'tecnico_ejecutor_id' => $tecnico->id,
        'fecha_inicio' => now()->subHours(2),
        'fecha_fin' => now()->subHour(),
        'tiempo_invertido_minutos' => 60,
        'actividades_realizadas' => 'Reparación completa.',
    ]);

    Evidencia::create([
        'incidencia_id' => $incidencia->id,
        'etapa' => 'EJECUCION',
        'referencia_tipo' => 'actividad_mantenimiento',
        'referencia_id' => $actividad->id,
        'nombre_original' => 'final.png',
        'disco' => 'evidencias',
        'ruta_storage' => 'incidencias/final.png',
        'mime_type' => 'image/png',
        'tamano_bytes' => 100,
        'hash_sha256' => str_repeat('a', 64),
        'usuario_carga_id' => $tecnico->id,
        'created_at' => now()->subHour(),
    ]);

    return $incidencia;
}
