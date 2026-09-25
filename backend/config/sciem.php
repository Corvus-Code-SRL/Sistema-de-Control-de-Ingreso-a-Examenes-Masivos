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

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de los exámenes
    |--------------------------------------------------------------------------
    |
    | examen.fecha y examen.hora_inicio guardan la hora local del campus, sin
    | zona. La aplicación corre en UTC, así que "ahora" se calcula en esta zona
    | para decidir si un examen se programa en el pasado.
    |
    */

    'zona_horaria' => env('SCIEM_ZONA_HORARIA', 'America/La_Paz'),

    /*
    |--------------------------------------------------------------------------
    | Código SIS de estudiante (carga de nómina)
    |--------------------------------------------------------------------------
    |
    | Los códigos SIS de estudiante son solo dígitos: 9 hoy, 8 antes del 2000. Esta
    | regla es solo de la nómina de estudiantes. Docentes y administradores tienen
    | códigos más cortos y alfanuméricos: no reutilizarla en el registro de cuentas.
    |
    */

    'estudiante_cod_sis' => [
        'min_length' => (int) env('SCIEM_ESTUDIANTE_COD_SIS_MIN', 8),
        'max_length' => (int) env('SCIEM_ESTUDIANTE_COD_SIS_MAX', 12),
    ],

];
