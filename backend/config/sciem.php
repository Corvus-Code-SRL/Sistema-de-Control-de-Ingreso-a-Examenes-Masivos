<?php

use App\Models\Role;

return [

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

    /*
    |--------------------------------------------------------------------------
    | Discos de las evidencias de reportes
    |--------------------------------------------------------------------------
    |
    | reporte_estudiante.evidencia_public_id guarda la ruta relativa del archivo,
    | siempre dentro de evidencias/. La Central de Riesgo lo busca en estos discos,
    | en orden: primero el privado (storage/app, donde HU-042 guardará lo nuevo) y
    | luego el público (donde el seeder de prueba dejó los archivos). El archivo se
    | lee del disco y se sirve por la API: no depende de storage:link ni de APP_URL.
    |
    */

    'evidencia_discos' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SCIEM_EVIDENCIA_DISCOS', 'local,public'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Acceso por función y rol
    |--------------------------------------------------------------------------
    |
    | ÚNICO lugar del backend que decide qué roles ejercen cada función
    | (UserRoleService::canUse). Cambiar el acceso de un rol es editar su lista aquí.
    |
    | DEBE mantenerse igual que ACCESS_BY_FEATURE en
    | frontend/src/features/auth/capabilities.ts: el menú y las rutas del cliente salen de ese
    | espejo, y capabilities.access.test.ts falla si los dos mapas difieren.
    |
    | El Auxiliar no figura: su acceso (control de ingreso, reportes de falta) es del rol, no de
    | estas funciones. El Invitado (docente sin código SIS) ejerce la docencia, habilita auxiliares
    | por examen, toma incidencias y opera el ingreso; no consulta la Central de Riesgo, no gestiona
    | auxiliares de grupo, no reporta auxiliares, no acepta casos especiales y, mientras no haya una
    | decisión de producto, no invita a otros docentes.
    |
    */

    'access' => [
        'teaching'         => [Role::DOCENTE, Role::INVITADO],
        'exam_assistants'  => [Role::DOCENTE, Role::INVITADO],
        'incidents'        => [Role::DOCENTE, Role::INVITADO],
        'risk_center'      => [Role::ADMINISTRADOR, Role::DOCENTE],
        'group_assistants' => [Role::DOCENTE],
        'report_assistant' => [Role::DOCENTE],
        'special_cases'    => [Role::DOCENTE],
        'invite_teachers'  => [Role::DOCENTE],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cuentas de sistema que nunca pueden iniciar sesión
    |--------------------------------------------------------------------------
    |
    | Códigos (separados por comas) de las cuentas que firman la bitácora de las automatizaciones.
    | AuthService las rechaza con el mismo mensaje genérico que una contraseña incorrecta,
    | aunque la contraseña sea la correcta y sea cual sea su rol. Ver App\Support\SystemAccounts.
    |
    */

    'cuentas_sistema' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SCIEM_CUENTAS_SISTEMA', '000000000'))
    ))),

];
