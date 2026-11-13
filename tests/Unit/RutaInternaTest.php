<?php

use App\Services\AutenticacionService;

it('acepta como deep-link solo rutas internas del front', function (?string $ruta, bool $esperado) {
    expect(AutenticacionService::esRutaInterna($ruta))->toBe($esperado);
})->with([
    'ruta simple' => ['/mis-tareas', true],
    'ruta con id' => ['/mis-tareas/105', true],
    'ruta con query' => ['/consola?estado=E05', true],
    'raíz' => ['/', true],
    'protocolo relativo' => ['//sitio-malicioso.com', false],
    'url absoluta' => ['https://sitio-malicioso.com/panel', false],
    'sin barra inicial' => ['panel', false],
    'barra invertida' => ['/\\sitio-malicioso.com', false],
    'con espacios' => ['/panel /x', false],
    'vacía' => ['', false],
    'nula' => [null, false],
]);
