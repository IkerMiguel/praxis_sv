<?php

namespace Database\Seeders;

use App\Models\Especialidad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsuariosSeeder extends Seeder
{
    public const PASSWORD = 'Praxis*2026';

    /**
     * Un usuario de prueba por rol, técnicos con especialidad y un usuario multi-rol (D03).
     */
    public function run(): void
    {
        $usuarios = [
            ['reportante@praxis.test', 'Laura Reportante', ['R01'], []],
            ['gestor@praxis.test', 'Gabriel Gestor', ['R02'], []],
            ['coordinador@praxis.test', 'Camila Coordinadora', ['R03'], []],
            ['tecnico@praxis.test', 'Tomás Técnico Electricista', ['R04'], ['ELECTRICIDAD', 'GENERAL']],
            ['plomero@praxis.test', 'Pedro Técnico Plomero', ['R04'], ['HIDROSANITARIO', 'OBRA_CIVIL']],
            ['cerrajero@praxis.test', 'Ciro Técnico Cerrajero', ['R04'], ['CERRAJERIA', 'CARPINTERIA']],
            ['autorizador@praxis.test', 'Andrea Autorizadora', ['R05'], []],
            ['verificador@praxis.test', 'Valentina Verificadora', ['R06'], []],
            ['admin@praxis.test', 'Alberto Administrador', ['R07'], []],
            ['docente.verificador@praxis.test', 'Diana Docente Verificadora', ['R01', 'R06'], []],
        ];

        $roles = Rol::query()->pluck('id', 'codigo');
        $especialidades = Especialidad::query()->pluck('id', 'codigo');

        foreach ($usuarios as [$email, $nombre, $codigosRol, $codigosEspecialidad]) {
            $usuario = User::updateOrCreate(['email' => $email], [
                'name' => $nombre,
                'password' => self::PASSWORD,
                'activo' => true,
            ]);

            $usuario->roles()->sync(collect($codigosRol)->mapWithKeys(fn (string $codigo) => [$roles[$codigo] => ['fecha_asignacion' => now()]])->all());
            $usuario->especialidades()->sync(collect($codigosEspecialidad)->map(fn (string $codigo) => $especialidades[$codigo])->all());
        }
    }
}
