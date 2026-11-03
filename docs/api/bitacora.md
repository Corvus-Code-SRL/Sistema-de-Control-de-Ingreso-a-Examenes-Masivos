# API de la Bitácora (HU-015)

Consulta **de solo lectura** de las operaciones que dejaron huella en el sistema (`bitacora`), de la más reciente a la más antigua, para el **Administrador**. La bitácora la escribe el servidor al ejecutar cada operación (`AuditLogService`); esta API únicamente la lee.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `meta`/`links` en el listado); los errores llevan `message` (y `errors` por campo en los 422).

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/bitacora` | Listado paginado (50 por página), con filtros |
| GET | `/api/bitacora/opciones` | Acciones y módulos de los selectores |
| GET | `/api/bitacora/{id}` | Detalle de una operación, con los cambios campo por campo |

No existe ninguna ruta de escritura (POST, PUT, PATCH, DELETE) bajo `/api/bitacora`: `AuditLogAccessTest` comprueba que responden 404/405. Consultar la bitácora **no genera entradas de bitácora**.

## Quién puede consultar

Solo un **Administrador** con la cuenta **activa** (`config('sciem.access.audit_log')`, capacidad `bitacora.consultar` en la interfaz). Cualquier otro rol, una cuenta sin rol o deshabilitada recibe **403** con «No tiene permiso para ver esta sección.»; sin token, **401**.

Middlewares: `auth:sanctum`, `account.active`, `password.changed` y el limitador `api`. La interfaz no consulta por intervalos (no hay *polling*).

## GET /api/bitacora

| Parámetro | Regla |
|---|---|
| `q` | Opcional, hasta 100 caracteres. Coincidencia **parcial**, sin distinguir mayúsculas ni tildes, sobre nombre completo o código SIS del usuario que hizo la operación. Cada palabra debe coincidir (hasta 5). También encuentra las operaciones **automáticas** cuyo docente del examen coincide |
| `accion` | Opcional. Código de `GET /api/bitacora/opciones` (`MODIFICAR`, `CREAR`…); solo `A-Z`, `0-9` y `_` |
| `modulo` | Opcional. Clave de `GET /api/bitacora/opciones`, o `otros` (tablas sin módulo asignado) |
| `desde`, `hasta` | Opcionales, `YYYY-MM-DD`. Días calendario de **La Paz** (`America/La_Paz`), ambos extremos incluidos. `hasta` debe ser igual o posterior a `desde`; si no, **422** con «La fecha de inicio debe ser anterior a la fecha de fin.» |
| `page` | Opcional, entero entre 1 y 100000. Una página sin filas responde 200 con `data: []` |

Un valor fuera de rango responde **422** con `errors` por campo. Orden: `fecha_hora` descendente y, a igual fecha, el id más reciente primero.

El rango se resuelve como intervalo semiabierto `[desde 00:00 La Paz, hasta + 1 día 00:00 La Paz)` convertido a UTC con desplazamiento explícito; **no depende de la zona horaria de la sesión de la base de datos**. Una operación a las 23:30 del 19/10 en La Paz (03:30 UTC del 20/10) entra en el día 19.

**200**

```json
{
  "data": [
    {
      "id_log": 130,
      "fecha_hora": "2026-10-20T14:21:00+00:00",
      "usuario": {
        "id_usuario": "…",
        "nombre_completo": "Marcelo Quiroga Andrade",
        "cod_sis": "10452",
        "tipo": "persona",
        "etiqueta": "Marcelo Quiroga Andrade"
      },
      "accion": { "codigo": "MODIFICAR", "etiqueta": "Modificar" },
      "modulo": { "clave": "grupos", "etiqueta": "Grupos" },
      "registro": { "tabla": "grupo", "etiqueta": "Grupo", "descripcion": "Grupo #7" },
      "automatico": false
    }
  ],
  "links": { "first": "…", "last": "…", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 50, "to": 1, "total": 1 }
}
```

`usuario.tipo`:

| Valor | Cuándo | `etiqueta` |
|---|---|---|
| `persona` | Cuenta de una persona | Nombre completo (con `cod_sis`) |
| `sistema` | Cuenta de sistema (`SystemAccounts`) | «Sistema» |
| `automatico` | Inicio o cierre automático de un examen (`nuevo_valor.tipo_inicio = AUTOMATICO` o `tipo_finalizacion = AUTOMATICA`) | «Automático» |

Una acción sin etiqueta conocida conserva el texto crudo de `operacion` en `accion.etiqueta` (`accion.codigo` puede ser `null`). Las fechas son ISO 8601 (UTC); la interfaz las muestra en `America/La_Paz`.

## GET /api/bitacora/opciones

**200**

```json
{
  "data": {
    "acciones": [{ "codigo": "MODIFICAR", "etiqueta": "Modificar" }],
    "modulos": [{ "clave": "grupos", "etiqueta": "Grupos" }, { "clave": "otros", "etiqueta": "Otros" }]
  }
}
```

Los módulos salen de `config('sciem.bitacora.modulos')`. **La Central de Riesgo no figura** en el selector: sus contextos académico y de admisión no se mezclan.

## GET /api/bitacora/{id}

`{id}` son hasta 9 dígitos; un id que no existe o no es numérico responde **404** (nunca 500).

**200**

```json
{
  "data": {
    "id_log": 130,
    "fecha_hora": "2026-10-20T14:21:00+00:00",
    "usuario": { "…": "como en el listado" },
    "accion": { "codigo": "MODIFICAR", "etiqueta": "Modificar" },
    "modulo": { "clave": "grupos", "etiqueta": "Grupos" },
    "registro": { "tabla": "grupo", "etiqueta": "Grupo", "descripcion": "Grupo #7", "ids": { "id_grupo": 7 } },
    "automatico": false,
    "docente_examen": null,
    "cambios": [
      { "campo": "num_grupo", "etiqueta": "Número de grupo", "oculto": false, "anterior": 3, "nuevo": 5 }
    ]
  }
}
```

- **Modificar**: solo los campos que cambiaron.
- **Crear**: `anterior` vacío (`null`) en cada campo.
- **Eliminación** (registro borrado): `nuevo` vacío (`null`) en cada campo.
- **Automática**: `docente_examen` trae `{ nombre_completo, cod_sis }` del docente dueño del examen; en las demás es `null`.
- El **orden de `cambios` no está garantizado** (PostgreSQL reordena las claves de `jsonb`); la interfaz identifica cada campo por `campo`.

## Campos ocultos

Los valores crudos (`valor_anterior`, `nuevo_valor`) **nunca** viajan. Un campo sensible aparece como `{ "campo": "…", "etiqueta": "…", "oculto": true }`, **sin `anterior` ni `nuevo`**, y la interfaz muestra «(oculto)». Dentro de estructuras anidadas, el valor se reemplaza por `"(oculto)"`.

Se ocultan (sin distinguir mayúsculas, tildes ni separadores, así que `Password`, `API_KEY` o `rememberToken` también):

- Nombres que **contienen**: `contrasenia`, `contrasena`, `password`, `passwd`, `token`, `secret`, `apikey`, `remember`.
- Nombres **exactos**: `evidencia_url`, `evidencia_public_id`.
- **Excepción**: los indicadores booleanos `debe_cambiar_contrasenia` y `contrasenia_cambiada` se muestran, y solo si su valor es booleano o `null`.

Se muestran, por ser parte de lo que el Administrador necesita auditar: correo, IP, motivo y los textos libres. La lista vive en `config('sciem.bitacora.campos_ocultos')` y la aplica `App\Support\Audit\AuditLogSanitizer`.

## Limitación conocida: cuenta de sistema y ADM0001

En la base actual la cuenta de sistema y el administrador `ADM0001` **comparten el mismo `id_usuario`**. La bitácora solo guarda ese id y el tipo de autor («Sistema» o persona) se decide por el código SIS de la cuenta a la que el id resuelve (`SystemAccounts::isSystemCode`). Con un id compartido no hay forma de distinguir quién firmó: las operaciones de ambos aparecen con la misma identidad, sea «Sistema» o la del administrador.

El código **no** lo rodea a propósito. Queda resuelto al reconstruir Supabase con `ADM0000X` con su propio `id_usuario`; no requiere cambios en esta API.

## Despliegue

Solo código y `config/sciem.php` (clave `access.audit_log` y bloque `bitacora`). **Sin migraciones ni seeders.** Tras desplegar, `php artisan config:clear` si la configuración está cacheada.
