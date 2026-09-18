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

];
