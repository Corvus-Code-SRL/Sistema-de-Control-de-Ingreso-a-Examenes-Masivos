# API de autenticación (RNF-02, fase 1)

Inicio de sesión con **tokens de Sanctum**. En esta fase el login funciona de punta a punta, pero **ninguna ruta existente exige token todavía** y las operaciones siguen atribuidas al usuario fijo (`CurrentUser`). La protección de rutas llega en las fases siguientes.

Convenciones: prefijo `/api`, JSON, cabecera `Accept: application/json`. Las respuestas correctas llevan `data` (y `mensaje` cuando aplica); los errores llevan `message` y, en los de autenticación, `motivo`. El token viaja en `Authorization: Bearer <token>`.

| Método | Ruta | Autenticación |
|---|---|---|
| POST | `/api/auth/login` | No — `throttle:5,1` |
| POST | `/api/auth/logout` | Token |
| GET | `/api/auth/yo` | Token |
| POST | `/api/auth/confirmar-password` | Token |

## Sesión y token

- Cada token es una sesión. Un mismo usuario puede tener varias a la vez.
- **Vence a las 12 horas de emitido** (`SANCTUM_TOKEN_EXPIRATION`, en minutos, 720 por defecto). No hay vencimiento por inactividad.
- El **rol vigente** es la asignación abierta del usuario (`usuario_rol.fecha_fin IS NULL`), resuelta en una consulta. Si hubiera varias abiertas se toma la de `fecha_inicio` más reciente.

## POST /api/auth/login

Cuerpo:

| Campo | Regla |
|---|---|
| `cod_sis` | obligatorio, texto. Se **normaliza** (recorta, mayúsculas, colapsa espacios) y **no** se valida formato ni longitud: conviven 5 dígitos (docentes), 9 (auxiliares y estudiantes) y alfanuméricos (Administrador) |
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
      "estado": "ACTIVO"
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
| Código inexistente **o** contraseña incorrecta | 401 | `credenciales_invalidas` | `Código SIS o contraseña incorrectos.` (idéntico en ambos casos: no revela si la cuenta existe) |
| Cuenta inactiva (con contraseña correcta) | 403 | `cuenta_inactiva` | `Su cuenta está deshabilitada. Contacte al Administrador.` |
| Sin rol vigente (con contraseña correcta) | 403 | `sin_rol_vigente` | `Su cuenta no tiene un rol vigente. Contacte al Administrador.` |
| Campos vacíos | 422 | — | errores de validación por campo |
| Más de 5 intentos por minuto desde la misma IP | 429 | — | `Too Many Attempts.` (sin `motivo`; ver «Límite de intentos») |

El estado de la cuenta y su rol solo se revelan cuando la contraseña es correcta.

**Un 401 no revela si la cuenta existe.** «Código inexistente» y «contraseña incorrecta» responden el mismo estado, el mismo `message` y el mismo `motivo`, con el cuerpo idéntico byte a byte; un test lo comprueba. Los dos 403 sí son distinguibles entre sí, y solo se alcanzan con la contraseña correcta:

| Pantalla del cliente | Condición | Estado | `motivo` |
|---|---|---|---|
| Credenciales incorrectas | código inexistente o contraseña errónea | 401 | `credenciales_invalidas` |
| Panel gris «cuenta inactiva», sin formulario (reintentar no sirve) | `usuario.estado = INACTIVO` | 403 | `cuenta_inactiva` |
| Panel azul «cuenta sin rol», con acción para pedir el rol al Administrador | sin asignación de rol abierta | 403 | `sin_rol_vigente` |

### Límite de intentos (429)

`POST /api/auth/login` admite 5 intentos por minuto y por IP (`throttle:5,1`); el sexto responde 429 con `{ "message": "Too Many Attempts." }` y estas cabeceras:

| Cabecera | Valor |
|---|---|
| `Retry-After` | segundos enteros hasta poder reintentar (entre 1 y 60; en las pruebas, `59` al sexto intento inmediato) |
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
                 "correo": "marcelo.quiroga@sciem.test", "estado": "ACTIVO" },
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

## Esquema y puesta en marcha

- `personal_access_tokens` no está en `creation-script.sql`; la crea la migración de Laravel/Sanctum. Su `tokenable_id` era `bigint` y `usuario.id_usuario` es `uuid`, así que la migración `2026_10_02_000000_adapt_personal_access_tokens_for_uuid_users` lo pasa a `uuid` (descartando los tokens previos, que no podían apuntar a nadie) y agrega `password_confirmado_en`.
- En la base compartida: `php artisan migrate` (crea la tabla y aplica el ajuste) y `php artisan db:seed --class=ActionSeeder` (idempotente; agrega la acción `INICIO_SESION_FALLIDO`). Coordinar con el equipo antes de migrar.
