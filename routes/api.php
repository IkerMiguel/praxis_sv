<?php

use App\Http\Controllers\Api\V1\AdminCategoriaController;
use App\Http\Controllers\Api\V1\AdminParametroController;
use App\Http\Controllers\Api\V1\AdminUbicacionController;
use App\Http\Controllers\Api\V1\AdminUsuarioController;
use App\Http\Controllers\Api\V1\AnaliticaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\ConsolaController;
use App\Http\Controllers\Api\V1\EjecucionController;
use App\Http\Controllers\Api\V1\MisSolicitudesController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\PanelController;
use App\Http\Controllers\Api\V1\PlanificacionController;
use App\Http\Controllers\Api\V1\RadicacionController;
use App\Http\Controllers\Api\V1\RecursosController;
use App\Http\Controllers\Api\V1\TriajeController;
use App\Http\Controllers\Api\V1\VerificacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API PRAXIS v1
|--------------------------------------------------------------------------
|
| Roles (D03): R01 Reportante, R02 Gestor, R03 Coordinador, R04 Técnico,
| R05 Autorizador, R06 Verificador, R07 Administrador. El middleware "rol"
| valida el rol activo (cabecera X-Active-Role) contra la matriz D09.
|
*/

Route::pattern('incidencia', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
Route::pattern('id', '[0-9]+');

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:login');
        Route::post('solicitar-recuperacion', [AuthController::class, 'solicitarRecuperacion'])->middleware('throttle:recuperacion');
        Route::get('restablecer-password/{token}', [AuthController::class, 'validarEnlace'])->middleware('throttle:recuperacion');
        Route::post('restablecer-password', [AuthController::class, 'restablecerPassword'])->middleware('throttle:recuperacion');
    });

    Route::get('evidencias/{evidencia}/descargar', [RadicacionController::class, 'descargar'])
        ->middleware('signed')
        ->name('evidencias.descargar');

    Route::middleware(['auth:sanctum', 'rol.activo', 'throttle:api'])->group(function (): void {
        $todos = 'rol:R01,R02,R03,R04,R05,R06,R07';
        $operativos = 'rol:R02,R03,R04,R05,R06,R07';

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // E02: shell, navegación, panel, ayuda y notificaciones.
        Route::get('navegacion/menu', [CatalogoController::class, 'menu']);
        Route::get('catalogo/glosario-estados', [CatalogoController::class, 'glosarioEstados']);
        Route::get('catalogo/ubicaciones/arbol-activo', [CatalogoController::class, 'arbolUbicaciones']);
        Route::get('catalogo/categorias', [CatalogoController::class, 'categorias']);
        Route::get('catalogo/prioridades', [CatalogoController::class, 'prioridades']);
        Route::get('panel/resumen', [PanelController::class, 'resumen'])->middleware($todos);
        Route::get('notificaciones', [NotificacionController::class, 'index']);
        Route::patch('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas']);
        Route::patch('notificaciones/{notificacion}/leer', [NotificacionController::class, 'leer'])->whereNumber('notificacion');

        // E03: radicación, evidencias y duplicados (D09: crear ticket R01, R02, R03, R04, R07).
        Route::middleware('rol:R01,R02,R03,R04,R07')->group(function (): void {
            Route::post('incidencias/radicar', [RadicacionController::class, 'radicar']);
            Route::get('incidencias/coincidencias-activas', [RadicacionController::class, 'coincidencias']);
            Route::post('incidencias/{incidencia}/suscribir-seguimiento', [RadicacionController::class, 'suscribir']);
        });
        Route::post('incidencias/evidencias/upload', [RadicacionController::class, 'subirEvidencias']);

        // E04: mis solicitudes (todos los roles ven las propias).
        Route::middleware($todos)->group(function (): void {
            Route::get('mis-solicitudes', [MisSolicitudesController::class, 'index']);
            Route::get('mis-solicitudes/{incidencia}', [MisSolicitudesController::class, 'show']);
            Route::post('mis-solicitudes/{incidencia}/aclaraciones', [MisSolicitudesController::class, 'responderAclaracion']);
            Route::post('incidencias/{incidencia}/cancelar-solicitante', [MisSolicitudesController::class, 'cancelar']);
            Route::post('incidencias/{incidencia}/reabrir', [MisSolicitudesController::class, 'reabrir']);
        });
        Route::middleware('rol:R01,R07')->group(function (): void {
            Route::post('incidencias/{incidencia}/confirmar', [MisSolicitudesController::class, 'confirmar']);
            Route::post('incidencias/{incidencia}/rechazar-conformidad', [MisSolicitudesController::class, 'rechazarConformidad']);
        });

        // E05: triaje, duplicados, clasificación y riesgo (R02; D09 permite devolver también a R03).
        Route::middleware('rol:R02,R07')->group(function (): void {
            Route::post('incidencias/{incidencia}/iniciar-revision', [TriajeController::class, 'iniciarRevision']);
            Route::get('incidencias/{incidencia}/posibles-duplicados', [TriajeController::class, 'posiblesDuplicados']);
            Route::post('incidencias/{incidencia}/vincular-duplicado', [TriajeController::class, 'vincularDuplicado']);
            Route::post('incidencias/{incidencia}/descartar-duplicado', [TriajeController::class, 'descartarDuplicado']);
            Route::post('incidencias/{incidencia}/clasificar', [TriajeController::class, 'clasificar']);
            Route::post('incidencias/{incidencia}/evaluacion-riesgo', [TriajeController::class, 'evaluarRiesgo']);
        });
        Route::post('incidencias/{incidencia}/solicitar-aclaracion', [TriajeController::class, 'solicitarAclaracion'])->middleware('rol:R02,R03,R07');
        Route::patch('incidencias/{incidencia}/prioridad', [TriajeController::class, 'cambiarPrioridad'])->middleware('rol:R02,R03,R07');
        Route::get('incidencias/{incidencia}', [ConsolaController::class, 'show'])->middleware($operativos);

        // E06 / E08 / E09: planificación, asignación, recursos, suspensión y cierre (R03).
        Route::middleware('rol:R03,R07')->group(function (): void {
            Route::get('tecnicos/disponibles', [PlanificacionController::class, 'tecnicosDisponibles']);
            Route::post('incidencias/{incidencia}/asignar', [PlanificacionController::class, 'asignar']);
            Route::post('incidencias/{incidencia}/programar', [PlanificacionController::class, 'programar']);
            Route::patch('incidencias/{incidencia}/reprogramar', [PlanificacionController::class, 'reprogramar']);
            Route::post('incidencias/{incidencia}/devolver-diagnostico', [PlanificacionController::class, 'devolverDiagnostico']);
            Route::post('incidencias/{incidencia}/cerrar-no-confirmada', [PlanificacionController::class, 'cerrarNoConfirmada']);
            Route::post('incidencias/{incidencia}/planificar-correccion', [PlanificacionController::class, 'planificarCorreccion']);
            Route::post('incidencias/{incidencia}/reanudar', [PlanificacionController::class, 'reanudar']);
            Route::post('incidencias/{incidencia}/anular', [PlanificacionController::class, 'anular']);
            Route::post('incidencias/{incidencia}/cierre-administrativo', [PlanificacionController::class, 'cierreAdministrativo']);
            Route::post('incidencias/{incidencia}/solicitudes-recursos', [RecursosController::class, 'solicitar']);
            Route::patch('incidencias/{incidencia}/confirmar-recursos', [RecursosController::class, 'confirmarRecursos']);
            Route::post('solicitudes-recursos/{id}/seguimientos', [RecursosController::class, 'seguimiento']);
        });
        Route::post('incidencias/{incidencia}/suspender', [PlanificacionController::class, 'suspender'])->middleware('rol:R03,R04,R07');

        // E08: autorizaciones (R05; R03 dentro de su tope de autoridad delegada).
        Route::get('autorizaciones/pendientes', [RecursosController::class, 'pendientes'])->middleware('rol:R03,R05,R07');
        Route::post('solicitudes-recursos/{id}/dictamen', [RecursosController::class, 'dictamen'])->middleware('rol:R03,R05,R07');

        // E07: inspección, diagnóstico y ejecución (R04 asignado).
        Route::middleware('rol:R04,R07')->group(function (): void {
            Route::get('tecnico/tareas', [EjecucionController::class, 'misTareas']);
            Route::post('incidencias/{incidencia}/iniciar-inspeccion', [EjecucionController::class, 'iniciarInspeccion']);
            Route::post('incidencias/{incidencia}/diagnostico', [EjecucionController::class, 'diagnostico']);
            Route::post('incidencias/{incidencia}/descartar-por-inspeccion', [EjecucionController::class, 'descartarPorInspeccion']);
            Route::post('incidencias/{incidencia}/iniciar-atencion', [EjecucionController::class, 'iniciarAtencion']);
            Route::post('incidencias/{incidencia}/registrar-intervencion', [EjecucionController::class, 'registrarIntervencion']);
        });

        // E09: verificación de calidad (R06).
        Route::middleware('rol:R06,R07')->group(function (): void {
            Route::get('verificacion/pendientes', [VerificacionController::class, 'pendientes']);
            Route::post('incidencias/{incidencia}/verificar', [VerificacionController::class, 'verificar']);
            Route::post('incidencias/{incidencia}/rechazar-verificacion', [VerificacionController::class, 'rechazar']);
        });

        // E10: consola, historial, plazos e indicadores.
        Route::get('consola/incidencias', [ConsolaController::class, 'index'])->middleware($operativos);
        Route::get('consola/plazos', [ConsolaController::class, 'plazos'])->middleware('rol:R02,R03,R05,R06,R07');
        Route::get('incidencias/{incidencia}/historial', [ConsolaController::class, 'historial'])->middleware($todos);
        Route::get('analitica/metricas-basicas', [AnaliticaController::class, 'metricasBasicas'])->middleware('rol:R02,R03,R05,R06,R07');

        // E01 / E11: administración (R07; R03 solo lectura según D09).
        Route::prefix('admin')->group(function (): void {
            Route::middleware('rol:R03,R07')->group(function (): void {
                Route::get('usuarios', [AdminUsuarioController::class, 'index']);
                Route::get('usuarios/{id}', [AdminUsuarioController::class, 'show']);
                Route::get('roles', [AdminUsuarioController::class, 'roles']);
                Route::get('especialidades', [AdminUsuarioController::class, 'especialidades']);
                Route::get('ubicaciones', [AdminUbicacionController::class, 'index']);
                Route::get('categorias', [AdminCategoriaController::class, 'index']);
                Route::get('parametros-proceso', [AdminCategoriaController::class, 'parametrosProceso']);
                Route::get('parametros', [AdminParametroController::class, 'index']);
                Route::get('parametros/historial', [AdminParametroController::class, 'historial']);
            });

            Route::middleware('rol:R07')->group(function (): void {
                Route::post('usuarios', [AdminUsuarioController::class, 'store']);
                Route::put('usuarios/{id}', [AdminUsuarioController::class, 'update']);
                Route::put('usuarios/{id}/roles', [AdminUsuarioController::class, 'actualizarRoles']);
                Route::patch('usuarios/{id}/estado', [AdminUsuarioController::class, 'cambiarEstado']);
                Route::post('ubicaciones/{nivel}', [AdminUbicacionController::class, 'store'])->whereIn('nivel', AdminUbicacionController::NIVELES);
                Route::put('ubicaciones/{nivel}/{id}', [AdminUbicacionController::class, 'update'])->whereIn('nivel', AdminUbicacionController::NIVELES);
                Route::patch('ubicaciones/{nivel}/{id}/estado', [AdminUbicacionController::class, 'cambiarEstado'])->whereIn('nivel', AdminUbicacionController::NIVELES);
                Route::delete('ubicaciones/{nivel}/{id}', [AdminUbicacionController::class, 'destroy'])->whereIn('nivel', AdminUbicacionController::NIVELES);
                Route::post('categorias', [AdminCategoriaController::class, 'store']);
                Route::put('categorias/{id}', [AdminCategoriaController::class, 'update']);
                Route::put('parametros-proceso', [AdminCategoriaController::class, 'actualizarParametrosProceso']);
                Route::put('parametros', [AdminParametroController::class, 'update']);
            });
        });
    });
});
