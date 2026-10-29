<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulosPermisosSeeder extends Seeder
{
    /**
     * Módulos de navegación y matriz de permisos D09. T = permitido, L = solo lectura, A = solo asignadas.
     */
    public function run(): void
    {
        $modulos = [
            ['PANEL', 'Inicio', '/panel', 'home', 'General', 10, ['R01' => 'T', 'R02' => 'T', 'R03' => 'T', 'R04' => 'T', 'R05' => 'T', 'R06' => 'T', 'R07' => 'T']],
            ['NUEVO_REPORTE', 'Nuevo reporte', '/reportes/nuevo', 'plus', 'General', 20, ['R01' => 'T', 'R02' => 'T', 'R03' => 'T', 'R04' => 'T', 'R07' => 'T']],
            ['MIS_SOLICITUDES', 'Mis solicitudes', '/mis-solicitudes', 'list', 'General', 30, ['R01' => 'T', 'R02' => 'T', 'R03' => 'T', 'R04' => 'T', 'R05' => 'T', 'R06' => 'T', 'R07' => 'T']],
            ['TRIAJE', 'Bandeja de triaje', '/revision', 'inbox', 'Gestión', 40, ['R02' => 'T', 'R03' => 'T', 'R07' => 'T']],
            ['PLANIFICACION', 'Planificación y asignación', '/planificacion', 'calendar', 'Gestión', 50, ['R03' => 'T', 'R07' => 'T']],
            ['MIS_TAREAS', 'Mis tareas', '/mis-tareas', 'wrench', 'Operación', 60, ['R03' => 'L', 'R04' => 'T', 'R07' => 'T']],
            ['AUTORIZACIONES', 'Autorizaciones', '/autorizaciones', 'check-circle', 'Operación', 70, ['R03' => 'L', 'R05' => 'T', 'R07' => 'T']],
            ['VERIFICACION', 'Verificación', '/verificacion', 'shield-check', 'Operación', 80, ['R03' => 'L', 'R06' => 'T', 'R07' => 'T']],
            ['CONSOLA', 'Consola de incidencias', '/consola', 'table', 'Consulta', 90, ['R02' => 'T', 'R03' => 'T', 'R04' => 'A', 'R05' => 'T', 'R06' => 'T', 'R07' => 'T']],
            ['PLAZOS', 'Plazos SLA', '/consola/plazos', 'clock', 'Consulta', 100, ['R02' => 'T', 'R03' => 'T', 'R05' => 'T', 'R06' => 'T', 'R07' => 'T']],
            ['INDICADORES', 'Indicadores', '/consola/indicadores', 'bar-chart', 'Consulta', 110, ['R02' => 'T', 'R03' => 'T', 'R05' => 'T', 'R06' => 'T', 'R07' => 'T']],
            ['ADMIN_USUARIOS', 'Usuarios y roles', '/admin/usuarios', 'users', 'Administración', 120, ['R03' => 'L', 'R07' => 'T']],
            ['ADMIN_UBICACIONES', 'Ubicaciones', '/admin/ubicaciones', 'map-pin', 'Administración', 130, ['R03' => 'L', 'R07' => 'T']],
            ['ADMIN_CATEGORIAS', 'Categorías de daño', '/admin/categorias', 'tag', 'Administración', 140, ['R03' => 'L', 'R07' => 'T']],
            ['ADMIN_PARAMETROS', 'Parámetros del sistema', '/admin/parametros', 'settings', 'Administración', 150, ['R03' => 'L', 'R07' => 'T']],
            ['AYUDA', 'Ayuda', '/ayuda', 'help-circle', 'General', 160, ['R01' => 'T', 'R02' => 'T', 'R03' => 'T', 'R04' => 'T', 'R05' => 'T', 'R06' => 'T', 'R07' => 'T']],
            ['CONFIRMAR_CIERRE', 'Confirmar cierre de ticket', '/mis-solicitudes', null, 'General', 170, ['R01' => 'T', 'R07' => 'T'], false],
        ];

        $niveles = ['T' => 'TOTAL', 'L' => 'LECTURA', 'A' => 'ASIGNADAS'];
        $roles = Rol::query()->pluck('id', 'codigo');

        foreach ($modulos as $definicion) {
            [$codigo, $etiqueta, $ruta, $icono, $grupo, $orden, $permisos] = $definicion;

            $modulo = Modulo::updateOrCreate(['codigo' => $codigo], [
                'etiqueta' => $etiqueta,
                'ruta' => $ruta,
                'icono' => $icono,
                'grupo' => $grupo,
                'orden' => $orden,
                'en_menu' => $definicion[7] ?? true,
            ]);

            DB::table('rol_modulo_permisos')->where('modulo_id', $modulo->id)->delete();

            foreach ($permisos as $rol => $nivel) {
                DB::table('rol_modulo_permisos')->insert([
                    'rol_id' => $roles[$rol],
                    'modulo_id' => $modulo->id,
                    'nivel_acceso' => $niveles[$nivel],
                ]);
            }
        }
    }
}
