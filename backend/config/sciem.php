<?php

return [

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
    | Cuenta de sistema (seeders y comandos)
    |--------------------------------------------------------------------------
    |
    | log.id_usuario es NOT NULL y apunta a public.usuario. Las escrituras que no
    | hace ninguna persona con sesión (seeders, comandos) necesitan un autor: este
    | uuid lo provee y UserSeeder siembra la fila correspondiente. No es un usuario
    | de la API. Solo lo lee App\Support\SystemActor (ver docs/architecture/usuario-actual.md).
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
    | Almacén del control de ingreso
    |--------------------------------------------------------------------------
    |
    | Solo el snapshot temporal de control de ingreso utiliza Redis. El resto
    | de la aplicación conserva el almacén definido por CACHE_DRIVER.
    |
    */

    'entry_control_cache_store' => env('ENTRY_CONTROL_CACHE_STORE', 'redis'),

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

    /*
    |--------------------------------------------------------------------------
    | Tope de filas de la nómina (carga CSV o XLSX)
    |--------------------------------------------------------------------------
    |
    | Máximo de filas de datos por archivo, sin contar el encabezado. Acota el
    | tiempo y la memoria de la lectura: un XLSX se carga entero en memoria y el
    | preview devuelve todas las filas. Un curso real tiene cientos de estudiantes.
    |
    */

    'nomina_max_filas' => (int) env('SCIEM_NOMINA_MAX_FILAS', 2000),

];
