<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Docente fijo
    |--------------------------------------------------------------------------
    |
    | Mientras la autenticación no esté implementada, el docente se resuelve
    | desde configuración. Corresponde a public.usuario.id_usuario, que es uuid.
    |
    */

    'docente_fijo_id' => env('SCIEM_DOCENTE_FIJO_ID'),

    /*
    |--------------------------------------------------------------------------
    | Periodo activo
    |--------------------------------------------------------------------------
    |
    | La tabla periodo no tiene ninguna columna que indique cuál está vigente,
    | así que el periodo activo se resuelve por configuración hasta que exista
    | una historia de usuario que lo administre.
    |
    */

    'periodo_activo_id' => env('SCIEM_PERIODO_ACTIVO_ID'),

    /*
    |--------------------------------------------------------------------------
    | Usuario de prueba de la bitácora
    |--------------------------------------------------------------------------
    |
    | log.id_usuario es NOT NULL y apunta a public.usuario. Sin sesión real,
    | la bitácora necesita un autor: este uuid lo provee y UserSeeder siembra
    | la fila correspondiente. Lleva valor por defecto para que la aplicación
    | funcione sin tocar el .env. Se elimina al implementar la autenticación.
    |
    */

    'usuario_prueba' => env('SCIEM_USUARIO_PRUEBA', '00000000-0000-4000-8000-000000000001'),

];
