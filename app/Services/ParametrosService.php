<?php

namespace App\Services;

use App\Models\ParametroSistema;
use App\Support\Parametro;
use Illuminate\Support\Facades\Cache;

/**
 * Lectura de parámetros del sistema con caché e invalidación al modificarlos (HU-11.3).
 */
class ParametrosService
{
    public const CACHE_KEY = 'praxis:parametros';

    /**
     * @return array<string, int|float|bool|string>
     */
    public function todos(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $valores = collect(Parametro::porDefecto())->map(fn (array $definicion) => (int) $definicion[0])->all();

            ParametroSistema::query()->get()->each(function (ParametroSistema $parametro) use (&$valores): void {
                $valores[$parametro->clave] = $parametro->valorTipado();
            });

            return $valores;
        });
    }

    public function entero(string $clave): int
    {
        return (int) ($this->todos()[$clave] ?? 0);
    }

    public function invalidar(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
