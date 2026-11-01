# API de autenticación (RNF-02)

Inicio de sesión con **tokens de Sanctum**. **El login es la única ruta pública de la API**: todas las demás exigen `Authorization: Bearer <token>` y, sin él, responden **401** en JSON (aunque el cliente no mande `Accept: application/json`). Cada operación se atribuye a la cuenta autenticada (`CurrentUser`); no existe ya un usuario fijo de respaldo.

Convenciones: prefijo `/api`, JSON, cabecera `Accept: application/json`. Las respuestas correctas llevan `data` (y `mensaje` cuando aplica); los errores llevan `message` y, en los de autenticación, `motivo`. El token viaja en `Authorization: Bearer <token>`.

| Método | Ruta | Autenticación |
|---|---|---|
| POST | `/api/auth/login` | No — `throttle:10,1` |
| POST | `/api/auth/logout` | Token |
| GET | `/api/auth/yo` | Token |
| POST | `/api/auth/confirmar-password` | Token |
| POST | `/api/auth/cambiar-contrasenia` | Token — `throttle:5,1` por cuenta |

## Rutas protegidas y roles

Las rutas de `routes/api/*.php` se cargan dentro de **un solo grupo `auth:sanctum`** (`routes/api.php`); el login se declara fuera de él. `RoutesRequireAuthenticationTest` recorre todas las rutas registradas y falla si alguna queda abierta.

Además del token, cada endpoint comprueba el **rol vigente de una cuenta ACTIVA**. Una cuenta deshabilitada con un token todavía vigente recibe 403:

| Endpoint | Quién puede |
|---|---|
| Registrar cuentas, verificar SIS, roles, materias y asignaciones a carreras, ambientes | Administrador activo |
| Grupos, nómina, exámenes (crear, modificar, cancelar, finalizar, asignar grupos), gestión de auxiliares y sus ambientes | Docente activo (y, además, dueño del grupo o examen) |
| Control de ingreso | El docente del examen o un auxiliar habilitado (`EntryAccessService`) |
| `GET /api/auxiliar/examenes` | Cualquier cuenta autenticada; solo devuelve lo que a esa cuenta le habilitaron |

Un Administrador **no** gestiona grupos ni exámenes, y un Docente no administra cuentas: no basta con «no ser auxiliar».

## Sesión y token

- Cada token es una sesión. Un mismo usuario puede tener varias a la vez.
- **Vence a las 12 horas de emitido** (`SANCTUM_TOKEN_EXPIRATION`, en minutos, 720 por defecto). No hay vencimiento por inactividad.
- El **rol vigente** es la asignación abierta del usuario (`usuario_rol.fecha_fin IS NULL`), resuelta en una consulta. Si hubiera varias abiertas se toma la de `fecha_inicio` más reciente.

## POST /api/auth/login

Cuerpo:

| Campo | Regla |
|---|---|
| `cod_sis` | obligatorio, texto. Es el código SIS o el código `INV` de un docente invitado (p. ej. `INV4821`). Se **normaliza** (recorta, pasa a mayúsculas, colapsa espacios), así que **no distingue mayúsculas de minúsculas**: `inv4821`, ` Inv4821 ` y `INV4821` son la misma cuenta, igual que `adm0001` y `ADM0001`. **No** se valida formato ni longitud: conviven 5 dígitos (docentes), 9 (auxiliares y estudiantes), alfanuméricos (Administrador) y `INV` + 4 dígitos (invitados) |
| `password` | obligatorio, texto |

**200**

```json
{
  "data": {
    "usuario": {
      "id_usuario": "00000000-0000-4000-8000-000000000011",
      "cod_sis": "10452",
      "nombre": "Marcelo",
      "apellido_paterno": "Quiroga",
      "apellido_materno": "Andrade",
      "nombre_completo": "Marcelo Quiroga Andrade",
      "correo": "marcelo.quiroga@sciem.test",
      "estado": "ACTIVO",
      "debe_cambiar_contrasenia": false
    },
    "rol": { "id_rol": 2, "nombre_rol": "Docente" },
    "token": "1|Qm9ndXNUb2tlbi4uLg",
    "tipo_token": "Bearer",
    "expira_en": "2026-10-03T09:00:00+00:00"
  },
  "mensaje": "Sesión iniciada correctamente."
}
```

Rechazos. **El contrato es `motivo`, no `message`:** el cliente decide con el código y muestra el texto; reescribir un mensaje no rompe nada, cambiar un código sí. Los tres códigos son estables y no se renombran. Todos los rechazos de autenticación tienen exactamente la forma `{ "message": "…", "motivo": "…" }`.

| Caso | Estado | `motivo` | `message` |
|---|---|---|---|
| Código inexistente, contraseña incorrecta **o** cuenta de sistema | 401 | `credenciales_invalidas` | `Código SIS o contraseña incorrectos.` (idéntico en los tres casos: no revela si la cuenta existe ni que es de sistema) |
| Cuenta inactiva (con contraseña correcta) | 403 | `cuenta_inactiva` | `Su cuenta está deshabilitada. Contacte al Administrador.` |
| Sin rol vigente (con contraseña correcta) | 403 | `sin_rol_vigente` | `Su cuenta no tiene un rol vigente. Contacte al Administrador.` |
| Campos vacíos | 422 | — | errores de validación por campo |
| Más de 10 intentos por minuto desde la misma IP | 429 | — | `Too Many Attempts.` (sin `motivo`; ver «Límite de intentos») |

El estado de la cuenta y su rol solo se revelan cuando la contraseña es correcta.

**Un 401 no revela si la cuenta existe.** «Código inexistente» y «contraseña incorrecta» responden el mismo estado, el mismo `message` y el mismo `motivo`, con el cuerpo idéntico byte a byte; un test lo comprueba. Los dos 403 sí son distinguibles entre sí, y solo se alcanzan con la contraseña correcta:

| Pantalla del cliente | Condición | Estado | `motivo` |
|---|---|---|---|
| Credenciales incorrectas | código inexistente o contraseña errónea | 401 | `credenciales_invalidas` |
| Panel gris «cuenta inactiva», sin formulario (reintentar no sirve) | `usuario.estado = INACTIVO` | 403 | `cuenta_inactiva` |
| Panel azul «cuenta sin rol», con acción para pedir el rol al Administrador | sin asignación de rol abierta | 403 | `sin_rol_vigente` |

### Cuentas de sistema

Las cuentas de sistema firman la bitácora de las automatizaciones (seeders, scheduler) y no son de ninguna persona, así que **nunca pueden iniciar sesión**: ni con la contraseña correcta ni con ningún rol. Se identifican por **código**, no por id, y la lista sale de la configuración:

| Configuración | Variable de entorno | Por defecto |
|---|---|---|
| `sciem.cuentas_sistema` | `SCIEM_CUENTAS_SISTEMA` (códigos separados por comas) | `000000000` |

- Se compara el código normalizado, así que la lista no distingue mayúsculas. Reemplazar la cuenta de sistema es cambiar esta lista, sin tocar el código.
- El rechazo es el mismo **401** `credenciales_invalidas` con el mismo mensaje y el mismo cuerpo que una contraseña incorrecta: no se revela que el código existe ni que es de sistema. Tampoco se comprueba el estado ni el rol (no hay 403).
- Para igualar los tiempos de respuesta se verifica la contraseña contra un hash de relleno.
- El intento queda en la bitácora (`INICIO_SESION_FALLIDO`) con `motivo = cuenta_de_sistema`, para distinguirlo de una contraseña errónea.
- En los datos de prueba el Administrador `ADM0001` comparte id con la cuenta de sistema y sí puede ingresar, justamente porque la regla va por código.

### Límite de intentos (429)

`POST /api/auth/login` admite 10 intentos por minuto y por IP (`throttle:10,1`); el undécimo responde 429 con `{ "message": "Too Many Attempts." }` y estas cabeceras:

| Cabecera | Valor |
|---|---|
| `Retry-After` | segundos enteros hasta poder reintentar (entre 1 y 60; en las pruebas, `59` al undécimo intento inmediato) |
| `X-RateLimit-Limit` | `5` |
| `X-RateLimit-Remaining` | `0` |
| `X-RateLimit-Reset` | marca de tiempo Unix en que se libera |

El frontend sirve la API desde otro origen (sin proxy), y el navegador solo deja leer a JavaScript las cabeceras de respuesta que el servidor expone. Por eso `config/cors.php` expone `Retry-After` (`Access-Control-Expose-Headers: Retry-After`): con él se arma la cuenta regresiva. Las demás cabeceras `X-RateLimit-*` llegan por la red pero no están expuestas: no deben usarse desde el cliente. Las respuestas 401 y 403 no llevan `Retry-After`.

**Bitácora.** Todo intento fallido de una cuenta existente queda en `log` con la acción `INICIO_SESION_FALLIDO`; `nuevo_valor` guarda `cod_sis`, `ip` y `motivo`, y `fecha_hora` la hora. `log.id_usuario` es `NOT NULL`, así que un código que no corresponde a ninguna cuenta no puede registrarse ahí: va al log de la aplicación (`Log::warning`) con los mismos datos. La contraseña nunca se registra.

## POST /api/auth/logout

Revoca **solo el token en uso**; las demás sesiones del usuario siguen abiertas.

**200**

```json
{ "data": { "sesion_cerrada": true }, "mensaje": "Sesión cerrada correctamente." }
```

El token revocado responde 401 en la petición siguiente.

## GET /api/auth/yo

Reconstruye la sesión al cargar la página: cuenta, rol vigente y lo necesario para pintar el menú. Responde igual que el login, sin token y con la navegación.

**200**

```json
{
  "data": {
    "usuario": { "id_usuario": "…", "cod_sis": "10452", "nombre": "Marcelo", "apellido_paterno": "Quiroga",
                 "apellido_materno": "Andrade", "nombre_completo": "Marcelo Quiroga Andrade",
                 "correo": "marcelo.quiroga@sciem.test", "estado": "ACTIVO", "debe_cambiar_contrasenia": false },
    "rol": { "id_rol": 2, "nombre_rol": "Docente" },
    "navegacion": {
      "permisos": ["examenes.gestionar"],
      "interfaces": ["Exámenes"]
    },
    "password_confirmado_en": "2026-10-02T21:00:00+00:00"
  }
}
```

- `permisos`: `permiso.nombre_permiso` del rol vigente (vía `permiso_rol`).
- `interfaces`: `ui.nombre_ui` de las pantallas que esos permisos habilitan (vía `ui_permiso`).
- Una fila `INACTIVO` en `permiso_rol`, `permiso`, `ui_permiso` o `ui` no concede nada.
- Si la cuenta se deshabilitó o perdió su rol después de iniciar sesión, responde 403 (`cuenta_inactiva` / `sin_rol_vigente`).
- Sin token, token vencido o revocado: **401**.

## POST /api/auth/confirmar-password

Verifica la contraseña del usuario autenticado y renueva el momento de confirmación de la sesión. Es el cimiento de RNF-06 (Central de Riesgo): **no hay ningún bloqueo ni vista que lo use todavía**.

Cuerpo: `{ "password": "…" }`

**200**

```json
{ "data": { "password_confirmado_en": "2026-10-02T21:30:00+00:00" }, "mensaje": "Contraseña confirmada." }
```

Contraseña incorrecta: **422** con error de validación en `password` (no 401, para que el cliente no cierre la sesión). El momento se guarda en `personal_access_tokens.password_confirmado_en`, también al iniciar sesión.

## POST /api/auth/cambiar-contrasenia

Cambia la contraseña propia. Es la salida de la contraseña temporal (HU-001) y la única ruta, junto con `GET /api/auth/yo` y `POST /api/auth/logout`, que responde mientras el cambio está pendiente.

```json
{ "contrasenia_actual": "Temporal2026", "contrasenia_nueva": "ClaveNueva2026", "contrasenia_nueva_confirmation": "ClaveNueva2026" }
```

- `contrasenia_actual` debe coincidir con la guardada.
- `contrasenia_nueva`: al menos 8 caracteres (72 como máximo, el límite de bcrypt), confirmada en `contrasenia_nueva_confirmation` y **distinta de la actual**.
- Limitada a **5 intentos por minuto y por cuenta** (429 al excederlos).

**200**

```json
{ "data": { "debe_cambiar_contrasenia": false }, "mensaje": "Contraseña actualizada correctamente." }
```

**422** con `errors.contrasenia_actual` (no coincide) o `errors.contrasenia_nueva` (corta, sin confirmar o igual a la actual). No es un 401, para que el cliente no cierre la sesión.

Al terminar: `usuario.debe_cambiar_contrasenia` pasa a `false`, se **revocan las demás sesiones de la cuenta** (se conserva la que hizo el cambio) y se registra `MODIFICAR` sobre `usuario` en la bitácora, **sin ningún valor de contraseña** en `antiguo_valor` ni `nuevo_valor`.

## Contraseña temporal de cuentas registradas (HU-001)

El Administrador escribe la **contraseña temporal** (`contrasenia_temporal`, mínimo 8 caracteres) al registrar la cuenta en `POST /api/usuarios` y se la entrega al usuario. Solo se guarda su hash: nunca se devuelve, se registra en la bitácora ni se envía por correo. La cuenta nace con `usuario.debe_cambiar_contrasenia = true`.

Mientras ese indicador sea `true`, **toda ruta protegida responde 403** con `motivo: "cambio_de_clave_pendiente"`, salvo:

| Ruta | Para qué |
|---|---|
| `GET /api/auth/yo` | El frontend lee `usuario.debe_cambiar_contrasenia` y decide a dónde ir |
| `POST /api/auth/cambiar-contrasenia` | Cerrar el pendiente |
| `POST /api/auth/logout` | Salir |

```json
{ "message": "Debe cambiar su contraseña temporal antes de continuar.", "motivo": "cambio_de_clave_pendiente" }
```

- El token **no se revoca**: la sesión sigue viva para poder cambiar la contraseña.
- `account.active` se evalúa antes: una cuenta deshabilitada recibe `cuenta_inactiva`.
- El login funciona igual y su respuesta (y la de `/auth/yo`) incluye `usuario.debe_cambiar_contrasenia`.
- Una cuenta sin rol vigente sigue sin poder iniciar sesión (`sin_rol_vigente`) hasta que se le asigne uno. Las cuentas anteriores a HU-001 tienen el indicador en `false` y no se ven afectadas.

## Cuentas de usuario (HU-001, HU-004)

Alta, consulta y verificación de cuentas. **Todas las rutas son exclusivas del Administrador activo**: cualquier otro rol, o una cuenta sin rol, recibe 403 con un `message` propio de la operación.

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/usuarios` | Listado paginado, con búsqueda y filtro por rol |
| GET | `/api/usuarios/codigo-invitado` | Código `INV` libre para un docente invitado |
| GET | `/api/sis/verificar/{cod_sis}` | Paso previo al registro |
| POST | `/api/usuarios` | Registrar una cuenta |
| POST | `/api/usuarios/{user}/rol` | Asignar o cambiar el rol (`{user}` es un uuid; otro valor responde 404) |

### Docente invitado

Un docente invitado es una cuenta **sin código SIS**. El sistema le genera un código de acceso `INV` + 4 dígitos (`INV1000` a `INV9999`, 9000 combinaciones) y, salvo que se pida lo contrario, la cuenta nace con el rol `Invitado` (la interfaz lo llama «Docente invitado»). Ese prefijo es suyo: ningún código SIS puede empezar con `INV`. Inicia sesión como cualquier otra cuenta, con su código `INV` en `cod_sis` (ver «POST /api/auth/login»).

Las respuestas de cuenta llevan `es_invitado` (`true` si el código empieza con `INV`) para que el cliente no tenga que deducirlo del código.

### GET /api/usuarios

Paginado en el servidor, ordenado por apellido paterno, apellido materno, nombre e id.

| Parámetro | Regla |
|---|---|
| `q` | opcional, hasta 50 caracteres. Coincide con el nombre completo o el código, **sin distinguir mayúsculas ni tildes**; cada palabra debe coincidir |
| `rol` | opcional. Id de un rol del catálogo (1 a 9 dígitos) o `sin-rol` para las cuentas sin rol vigente. Filtra por el rol **vigente** |
| `page` | opcional, entero de 1 a 100000 (por defecto 1) |
| `per_page` | opcional, entero de 1 a 50 (por defecto 10) |

Un valor que no cumpla la regla responde 422 con el error en el campo.

**200**

```json
{
  "data": [
    {
      "id_usuario": "9b04bd24-5b89-4496-b94b-7260618d7e9d",
      "cod_sis": "202312345",
      "es_invitado": false,
      "nombre": "Maya",
      "apellido_paterno": "Bayer",
      "apellido_materno": "Reilly",
      "nombre_completo": "Maya Bayer Reilly",
      "correo": "maya.bayer@sciem.test",
      "estado": "ACTIVO",
      "rol": { "id_rol": 3, "nombre_rol": "Auxiliar" }
    }
  ],
  "links": { "first": "…/api/usuarios?page=1", "last": "…/api/usuarios?page=3", "prev": null, "next": "…/api/usuarios?page=2" },
  "meta": {
    "current_page": 1, "from": 1, "last_page": 3, "path": "…/api/usuarios",
    "per_page": 5, "to": 5, "total": 13,
    "id_usuario_actual": "00000000-0000-4000-8000-000000000001"
  }
}
```

- `rol` es `null` en una cuenta sin rol vigente (el cliente pinta «Sin rol»).
- `meta` es la paginación estándar de Laravel (también trae `links`, la lista de páginas para pintar el paginador) más `id_usuario_actual`: la cuenta autenticada, para que la vista sepa cuál es la suya sin depender de otra llamada. `GET /api/usuarios/{user}` también la incluye.

### GET /api/usuarios/codigo-invitado

Propone un código `INV` que **ahora** está libre, para mostrárselo al Administrador antes de registrar la cuenta. No lo reserva.

**200**

```json
{ "data": { "cod_sis": "INV4821" } }
```

Si ya están en uso los 9000 códigos: **422** con `{ "message": "No quedan códigos disponibles para docentes invitados. …" }`.

### GET /api/sis/verificar/{cod_sis}

Paso previo al registro de una cuenta con código SIS. El código se normaliza igual que en el login. Se revisa **en este orden**:

| Caso | Estado | Respuesta |
|---|---|---|
| Ya existe una cuenta con ese código (sin distinguir mayúsculas) | 422 | error de validación en `errors.cod_sis` y `cuenta_existente.id_usuario` |
| El servicio del SIS no responde | 503 | `{ "message": "El servicio institucional (SIS) no está disponible. Intente más tarde." }` |
| El SIS **no conoce** el código | 200 | `{ "data": null }` |
| El SIS conoce a la persona | 200 | `data` con sus datos |

El duplicado se revisa antes que el SIS, así que se detecta aunque el SIS no responda. **Que el SIS no conozca el código no impide registrar la cuenta**: `data: null` solo significa que no hay datos que precargar y que quien registra los escribe.

```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": { "cod_sis": ["Ya existe una cuenta con este código SIS: Laura Mendoza Rivas."] },
  "cuenta_existente": { "id_usuario": "00000000-0000-4000-8000-000000000011" }
}
```

```json
{ "data": { "nombre": "Laura", "paterno": "Mendoza", "materno": "Rivas", "tipo": "Docente", "facultad": "Facultad de Ciencias y Tecnología" } }
```

`cuenta_existente.id_usuario` permite ofrecer «Ver la cuenta existente».

### POST /api/usuarios

Cuerpo:

| Campo | Regla |
|---|---|
| `sin_codigo_sis` | opcional, booleano. `true` registra un docente invitado |
| `cod_sis` | **obligatorio** si no es invitado (texto, hasta 15; se normaliza). **Prohibido** con `sin_codigo_sis`. Su formato depende del rol (ver abajo) |
| `cod_invitado` | solo con `sin_codigo_sis`; **prohibido** sin él. Opcional, formato `INV1234` (`INV` + 4 dígitos, el primero distinto de 0). Es el código que se le mostró al Administrador (`GET /usuarios/codigo-invitado`); sin él, el sistema genera uno |
| `id_rol` | opcional, entero, rol activo del catálogo |
| `nombre` | obligatorio, hasta 50 |
| `apellido_paterno` | obligatorio, hasta 60 |
| `apellido_materno` | opcional, hasta 60 |
| `correo` | obligatorio, correo válido, hasta 100, único |
| `contrasenia_temporal` | obligatorio, de 8 a 72 caracteres (ver «Contraseña temporal») |

**Rol inicial.**

| Caso | Rol con el que nace la cuenta |
|---|---|
| `id_rol` indicado | ese rol |
| Invitado sin `id_rol` en el cuerpo | `Invitado` |
| Invitado con `"id_rol": null` | sin rol |
| No invitado sin `id_rol` (o `null`) | sin rol |

**Formato de `cod_sis` según el rol elegido.** Ya no hace falta que el SIS conozca el código: basta con un formato válido. Un código que empiece con `INV` se rechaza siempre.

| Rol | Formato |
|---|---|
| Auxiliar | 9 dígitos cuyos 4 primeros son un año entre 1990 y el actual (`202312345`), u 8 dígitos que empiecen con 9 |
| Administrador | letras seguidas de dígitos (`ADM0001`) |
| Docente | alfanumérico de 3 a 15 caracteres (`10452`) |
| Sin rol | cualquiera de los anteriores |

**201**

```json
{
  "data": {
    "id_usuario": "…", "cod_sis": "INV4821", "es_invitado": true,
    "nombre": "Rosa", "apellido_paterno": "Vargas", "apellido_materno": null, "nombre_completo": "Rosa Vargas",
    "correo": "rosa.vargas@sciem.test", "estado": "ACTIVO",
    "rol": { "id_rol": 4, "nombre_rol": "Invitado" }
  },
  "mensaje": "Cuenta creada correctamente."
}
```

Cuenta, rol y bitácora se guardan en una sola transacción.

Rechazos:

| Caso | Estado | Respuesta |
|---|---|---|
| El código tiene formato válido para algún rol pero **no para el elegido** | 422 | `errors.id_rol` (no `cod_sis`: el código está bien, lo que no cuadra es la combinación) |
| El código no tiene un formato válido para ningún rol | 422 | `errors.cod_sis` |
| Código ya registrado (se compara sin distinguir mayúsculas) | 422 | `errors.cod_sis`: `Ya existe una cuenta registrada con este código SIS.` |
| Correo ya registrado | 422 | `errors.correo` |
| Invitado con un rol distinto de `Invitado` | 422 | `errors.id_rol`: `Una cuenta de docente invitado solo puede tener el rol Docente invitado.` |
| Cuenta con código SIS y rol `Invitado` | 422 | `errors.id_rol`: `El rol Docente invitado es solo para cuentas de docente invitado, sin código SIS.` |
| `cod_invitado` ya tomado por otra cuenta | **409** | ver abajo |
| Se agotaron los códigos `INV` | 422 | `message` (ver `codigo-invitado`) |
| No es Administrador | 403 | `Solo un Administrador puede registrar cuentas.` |

El 422 de un código que no cuadra con el rol tiene la forma estándar de validación:

```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": { "id_rol": ["El código SIS de un Administrador son letras seguidas de dígitos (por ejemplo ADM0001)."] }
}
```

**409 con `codigo_sugerido`.** Entre que se mostró el código y se registró la cuenta, otra la tomó. El registro **no se guarda** y la respuesta trae un código libre para volver a intentar:

```json
{
  "message": "El código INV4821 ya fue asignado a otra cuenta. Se propone el código INV7305.",
  "codigo_sugerido": "INV7305"
}
```

Lo mismo ocurre si la colisión se detecta recién en el INSERT. Sin `cod_invitado`, el sistema reintenta solo con otros códigos y no responde 409.

### POST /api/usuarios/{user}/rol

Cuerpo: `{ "id_rol": 2 }` (obligatorio, entero, rol activo del catálogo). Cierra el rol vigente y abre el nuevo; el historial no se borra. Una cuenta no puede cambiarse el rol a sí misma.

**El rol de un docente invitado no se cambia nunca**: nace con `Invitado` al registrarse y ese es el único que tiene. Se rechaza antes de mirar el rol pedido, incluso si es el mismo:

**422**

```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": { "id_rol": ["El rol de docente invitado no se puede cambiar."] }
}
```

A una cuenta con código SIS tampoco se le puede asignar `Invitado` (el mismo 422, con el mensaje de la tabla de `POST /api/usuarios`).

## Esquema y puesta en marcha

- `personal_access_tokens` no está en `creation-script.sql`; la crea la migración de Laravel/Sanctum. Su `tokenable_id` era `bigint` y `usuario.id_usuario` es `uuid`, así que la migración `2026_10_02_000000_adapt_personal_access_tokens_for_uuid_users` lo pasa a `uuid` (descartando los tokens previos, que no podían apuntar a nadie) y agrega `password_confirmado_en`.
- En la base compartida: `php artisan migrate` (crea la tabla y aplica el ajuste) y `php artisan db:seed --class=ActionSeeder` (idempotente; agrega la acción `INICIO_SESION_FALLIDO`). Coordinar con el equipo antes de migrar.
