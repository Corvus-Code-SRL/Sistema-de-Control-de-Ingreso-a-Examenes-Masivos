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
    | Límites de peticiones por minuto (RNF-03)
    |--------------------------------------------------------------------------
    |
    | Los lee App\Providers\RouteServiceProvider. Con sesión se limita por usuario, no por IP: detrás
    | del NAT del campus todos los controladores comparten IP.
    |
    | - api:       acciones y consultas de un usuario autenticado.
    | - polling:   las consultas periódicas (/estado del control de ingreso y /en-curso), aparte
    |              para que no consuman la cuota de las acciones.
    | - guest:     peticiones sin sesión que pasan por el limiter `api`, por IP.
    | - login_account: intentos de inicio de sesión sobre un mismo código SIS desde una misma IP.
    | - login_ip:  intentos de inicio de sesión desde una IP, sea cual sea el código.
    |
    */

    'rate_limits' => [
        'api' => (int) env('SCIEM_RATE_API', 180),
        'polling' => (int) env('SCIEM_RATE_POLLING', 90),
        'guest' => (int) env('SCIEM_RATE_GUEST', 60),
        'login_account' => (int) env('SCIEM_RATE_LOGIN_ACCOUNT', 10),
        'login_ip' => (int) env('SCIEM_RATE_LOGIN_IP', 300),
    ],

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
    | decisión de producto, no invita a otros docentes. La bitácora (audit_log) la consulta solo el
    | Administrador.
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
        'audit_log'        => [Role::ADMINISTRADOR],
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

    /*
    |--------------------------------------------------------------------------
    | Consulta de la bitácora (HU-015)
    |--------------------------------------------------------------------------
    |
    | Solo lectura sobre la tabla log. Nada de esto cambia cómo se escribe:
    |
    | - por_pagina: filas por página (fijo, no lo elige el cliente).
    | - modulos: módulos del filtro, en el orden en que se ofrecen. «otros» no se declara: es todo lo
    |   que no esté en `tablas`. La Central de Riesgo no figura porque nadie escribe en ella.
    | - tablas: cada tabla_afectada que hoy se registra, con su módulo, cómo se nombra el registro y
    |   las claves que lo identifican (la primera es la principal). `persona` resuelve esa clave a
    |   «Nombre (SIS)».
    | - etiquetas_accion: texto del filtro y de la lista; un código ausente se muestra tal cual.
    | - etiquetas_campo: nombre legible de los campos del detalle; si falta se deduce del nombre.
    | - campos_ocultos: nunca se devuelven (ver App\Support\Audit\AuditLogSanitizer). Se comparan sin
    |   distinguir mayúsculas ni separadores (rememberToken = remember_token). `fragmentos` oculta
    |   cualquier campo que los contenga; `exactos` solo ese nombre; `excepciones` son indicadores
    |   booleanos que contienen un fragmento pero no guardan ningún secreto.
    |
    */

    'bitacora' => [
        'por_pagina' => 50,

        'modulos' => [
            'cuentas' => 'Cuentas',
            'roles' => 'Roles',
            'grupos' => 'Grupos',
            'catalogo' => 'Catálogo académico',
            'ambientes' => 'Ambientes',
            'examenes' => 'Exámenes',
            'auxiliares' => 'Auxiliares',
            'ingreso' => 'Control de ingreso',
            'incidencias' => 'Incidencias',
            'situaciones' => 'Situaciones',
        ],

        'tablas' => [
            'usuario' => [
                'modulo' => 'cuentas', 'etiqueta' => 'Cuenta', 'claves' => ['id_usuario'], 'persona' => true,
            ],
            'usuario_rol' => [
                'modulo' => 'roles', 'etiqueta' => 'Rol de cuenta', 'claves' => ['id_usuario'], 'persona' => true,
            ],
            'grupo' => ['modulo' => 'grupos', 'etiqueta' => 'Grupo', 'claves' => ['id_grupo']],
            'grupo_estudiante' => ['modulo' => 'grupos', 'etiqueta' => 'Nómina de grupo', 'claves' => ['id_grupo']],
            'materia_carrera' => [
                'modulo' => 'catalogo', 'etiqueta' => 'Materia de carrera', 'claves' => ['id_materia', 'id_carrera'],
            ],
            'oferta_academica' => [
                'modulo' => 'catalogo', 'etiqueta' => 'Oferta académica', 'claves' => ['id_materia', 'id_carrera'],
            ],
            'ambiente' => ['modulo' => 'ambientes', 'etiqueta' => 'Ambiente', 'claves' => ['id_ambiente']],
            'examen' => ['modulo' => 'examenes', 'etiqueta' => 'Examen', 'claves' => ['id_examen']],
            'grupo_auxiliar' => [
                'modulo' => 'auxiliares', 'etiqueta' => 'Auxiliar de grupo', 'claves' => ['id_grupo'],
            ],
            'examen_auxiliar' => [
                'modulo' => 'auxiliares', 'etiqueta' => 'Auxiliar de examen', 'claves' => ['id_examen'],
            ],
            'examen_estudiante' => [
                'modulo' => 'ingreso',
                'etiqueta' => 'Ingreso de estudiante',
                'claves' => ['id_examen', 'id_estudiante'],
            ],
            'intento_ingreso' => [
                'modulo' => 'ingreso', 'etiqueta' => 'Intento de ingreso', 'claves' => ['id_intento'],
            ],
            'solicitud_ingreso_retraso' => [
                'modulo' => 'ingreso', 'etiqueta' => 'Solicitud de ingreso con retraso', 'claves' => ['id_solicitud'],
            ],
            'reporte_estudiante' => [
                'modulo' => 'incidencias', 'etiqueta' => 'Reporte de falta', 'claves' => ['id_reporte_est'],
            ],
            'situacion_examen' => [
                'modulo' => 'situaciones', 'etiqueta' => 'Situación de examen', 'claves' => ['id_situacion'],
            ],
        ],

        'etiquetas_accion' => [
            'CREAR' => 'Crear',
            'MODIFICAR' => 'Modificar',
            'DESHABILITAR' => 'Deshabilitar',
            'HABILITAR' => 'Habilitar',
            'ASIGNAR_ROL' => 'Asignar rol',
            'CANCELAR' => 'Cancelar',
            'INICIAR' => 'Iniciar',
            'FINALIZAR' => 'Finalizar',
            'INICIO_SESION_FALLIDO' => 'Inicio de sesión fallido',
            'ANADIR_AUXILIAR_GRUPO' => 'Añadir auxiliar a grupo',
            'HABILITAR_AUXILIAR_EXAMEN' => 'Habilitar auxiliar en examen',
            'QUITAR_AUXILIAR_GRUPO' => 'Quitar auxiliar de grupo',
            'QUITAR_AUXILIAR_EXAMEN' => 'Quitar auxiliar de examen',
            'APROBAR_REPORTE' => 'Aprobar reporte',
            'RECHAZAR_REPORTE' => 'Rechazar reporte',
            'SOLICITAR_INGRESO_RETRASO' => 'Solicitar ingreso con retraso',
            'AUTORIZAR_INGRESO_RETRASO' => 'Autorizar ingreso con retraso',
            'RECHAZAR_INGRESO_RETRASO' => 'Rechazar ingreso con retraso',
            'REGISTRAR_INGRESO_RETRASO' => 'Registrar ingreso con retraso',
            'EXPULSAR_ESTUDIANTE' => 'Retirar estudiante',
            'CAMBIAR_AMBIENTE' => 'Cambiar ambiente',
            'OBSERVAR_IDENTIDAD' => 'Observar identidad',
        ],

        'etiquetas_campo' => [
            'num_grupo' => 'Número de grupo',
            'id_gestion_periodo' => 'Gestión y período',
            'estado' => 'Estado',
            'estado_ingreso' => 'Estado de ingreso',
            'nro_aula' => 'Número de aula',
            'cod_sis' => 'Código SIS',
            'correo' => 'Correo',
            'debe_cambiar_contrasenia' => 'Debe cambiar la contraseña',
            'contrasenia_cambiada' => 'Contraseña cambiada',
            'tipo_inicio' => 'Tipo de inicio',
            'tipo_finalizacion' => 'Tipo de finalización',
            'inicio_real_en' => 'Inicio real',
            'ip' => 'Dirección IP',
            'motivo' => 'Motivo',
        ],

        'campos_ocultos' => [
            'fragmentos' => [
                'contrasenia', 'contrasena', 'password', 'passwd', 'token', 'secret', 'apikey', 'remember',
            ],
            'exactos' => ['evidencia_url', 'evidencia_public_id'],
            'excepciones' => ['debe_cambiar_contrasenia', 'contrasenia_cambiada'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Respaldo y restauración de la base
    |--------------------------------------------------------------------------
    |
    | sciem:respaldar guarda un pg_dump (formato custom, solo el esquema public) en `directorio` y deja
    | solo los `retencion` más recientes; sciem:restaurar lo vuelve a cargar. Ver
    | docs/database/respaldo-y-restauracion.md.
    |
    | - directorio: dónde se guardan. Vacío = storage/app/respaldos. En producción, una unidad persistente
    |   fuera de la carpeta del despliegue; nunca dentro de un repositorio de git.
    | - pg_dump / pg_restore: ruta de los binarios. Su versión debe ser igual o mayor que la del servidor.
    | - diario: si el planificador ejecuta el respaldo todos los días a la `hora` de La Paz. Apagado por
    |   defecto: solo se enciende en la máquina que respalda la base (el servidor de producción).
    | - restaurar_permitido: sciem:restaurar reemplaza los datos y por defecto se niega a ejecutarse. Se
    |   pone true solo mientras se restaura y se devuelve a false al terminar.
    | - timeout: segundos máximos de cada pg_dump o pg_restore.
    |
    */

    'respaldo' => [
        'directorio' => env('SCIEM_RESPALDO_DIRECTORIO') ?: storage_path('app/respaldos'),
        'retencion' => (int) env('SCIEM_RESPALDO_RETENCION', 7),
        'pg_dump' => env('SCIEM_PG_DUMP', 'pg_dump'),
        'pg_restore' => env('SCIEM_PG_RESTORE', 'pg_restore'),
        'diario' => (bool) env('SCIEM_RESPALDO_DIARIO', false),
        'hora' => env('SCIEM_RESPALDO_HORA', '03:15'),
        'restaurar_permitido' => (bool) env('SCIEM_RESTAURAR_PERMITIDO', false),
        'timeout' => (int) env('SCIEM_RESPALDO_TIMEOUT', 1800),
    ],

];
