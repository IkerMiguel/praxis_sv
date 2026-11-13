<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria operativa
    |--------------------------------------------------------------------------
    |
    | Zona en la que se calculan jornadas hábiles y plazos SLA. Las marcas de
    | tiempo se almacenan en UTC (D10).
    |
    */

    'zona_horaria' => env('PRAXIS_ZONA_HORARIA', 'America/Bogota'),

    /*
    |--------------------------------------------------------------------------
    | Tokens de sesión (D15)
    |--------------------------------------------------------------------------
    */

    'access_token_minutos' => (int) env('PRAXIS_ACCESS_TOKEN_MINUTOS', 15),

    'refresh_token_dias' => (int) env('PRAXIS_REFRESH_TOKEN_DIAS', 7),

    'refresh_cookie' => [
        'nombre' => env('PRAXIS_REFRESH_COOKIE', 'praxis_refresh'),
        'path' => '/api/v1/auth',
        'dominio' => env('PRAXIS_REFRESH_COOKIE_DOMAIN'),
        'secure' => (bool) env('PRAXIS_REFRESH_COOKIE_SECURE', true),
        'same_site' => env('PRAXIS_REFRESH_COOKIE_SAMESITE', 'none'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend (praxis_client)
    |--------------------------------------------------------------------------
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento de evidencias (RN-042)
    |--------------------------------------------------------------------------
    */

    'disco_evidencias' => env('PRAXIS_DISCO_EVIDENCIAS', 'evidencias'),

    'evidencias_url_minutos' => (int) env('PRAXIS_EVIDENCIAS_URL_MINUTOS', 30),

];
