<?php

namespace Database\Seeders;

use App\Models\ParametroSistema;
use App\Support\Parametro;
use Illuminate\Database\Seeder;

class ParametrosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Parametro::porDefecto() as $clave => [$valor, $tipo, $grupo, $descripcion]) {
            ParametroSistema::firstOrCreate(['clave' => $clave], [
                'valor' => $valor,
                'tipo_dato' => $tipo,
                'grupo' => $grupo,
                'descripcion' => $descripcion,
            ]);
        }
    }
}
