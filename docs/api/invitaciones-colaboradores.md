# API de invitación de docentes colaboradores (HU-026)

Permite al **Docente** que creó un examen **invitar a otros docentes** que dictan la misma materia, para dar un examen común. Esta historia termina cuando la invitación queda enviada con estado **PENDIENTE**: recibirla, aceptarla o rechazarla es de **HU-027**.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `mensaje` al crear); los errores llevan `message` (y `errors` por campo en los 422). Middlewares: `auth:sanctum`, `account.active`, `password.changed`.

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/docente/invitaciones/examenes` | Exámenes PROGRAMADOS propios (el desplegable) |
| GET | `/api/docente/invitaciones/examenes/{id_examen}/docentes?criterio=` | Busca docentes invitables a ese examen |
| POST | `/api/docente/invitaciones/examenes/{id_examen}` | Envía la invitación (queda PENDIENTE) |
| GET | `/api/docente/invitaciones` | Invitaciones enviadas por el docente, de la más reciente a la más antigua |

`{id_examen}` es de 1 a 9 dígitos; otro formato, o un examen que no existe, responde **404**. Estas rutas son solo de HU-026: la bandeja de invitaciones **recibidas** de HU-027 usa otras (`/api/invitaciones`).

## Quién puede usarla

Solo un **Docente** con la cuenta **activa** (capacidad `invite_teachers` en `config/sciem.php`, `docentes.invitar` en el cliente). Un docente con rol **Invitado**, un Auxiliar, un Administrador, una cuenta sin rol o deshabilitada recibe **403** («Solo un docente puede invitar colaboradores a un examen.»); sin token, **401**. Un docente que aceptó una invitación a un examen ajeno tampoco puede invitar a él: solo el creador.

## Reglas

- **Solo el creador** del examen invita a él. Otro docente recibe **403** («Solo el docente que creó el examen puede invitar colaboradores a él.»).
- **Solo mientras el examen está PROGRAMADO.** Con el control de ingreso ya abierto (o el examen cancelado o terminado) la búsqueda y el envío responden **409** («El control de ingreso del examen ya se inició: ya no se puede invitar docentes.» / «El examen está cancelado: ya no se puede invitar docentes.»), y el examen deja de salir en el desplegable.
- **Quién se puede invitar:** un docente con la cuenta **ACTIVA**, con rol **Docente o Invitado** (los roles que ejercen la docencia, `sciem.access.teaching`), que tenga al menos un **grupo ACTIVO de la materia del examen en el período vigente**. Se compara solo `id_materia`, no la carrera, igual que al asignar grupos a un examen. Nunca el propio docente.
- **Sin invitaciones repetidas:** no se puede invitar al mismo docente al mismo examen mientras su invitación esté **PENDIENTE o ACEPTADA** (**409**, «El docente ya tiene una invitación pendiente o aceptada para este examen.»). Tras **RECHAZADA** o **SIN_EFECTO** sí se lo puede invitar de nuevo.

## GET /api/docente/invitaciones/examenes

Los exámenes **PROGRAMADOS** del docente (con al menos un grupo del período vigente, o aún sin grupos), del más próximo al más lejano. Sin exámenes responde **200** con `data: []`.

```json
{
  "data": [
    {
      "id_examen": 9811,
      "nombre_examen": "HU-026 Parcial Cálculo I",
      "fecha": "2026-12-10",
      "hora_inicio": "08:00",
      "hora_fin": "09:30",
      "materia": { "id_materia": 9201, "nombre": "Cálculo I", "codigo": "MAT-101" },
      "carrera": { "id_carrera": 9101, "nombre": "Ingeniería de Sistemas" },
      "ambientes": [ { "id_ambiente": 9601, "nro_aula": "691A" }, { "id_ambiente": 9602, "nro_aula": "692B" } ]
    }
  ]
}
```

## GET /api/docente/invitaciones/examenes/{id_examen}/docentes

| Parámetro | Regla |
|---|---|
| `criterio` | Obligatorio, de 2 a 100 caracteres. Busca por **nombre o apellidos sin distinguir mayúsculas ni tildes** («jose» encuentra «José»), o por **código SIS**. |

Devuelve hasta 20 docentes, ordenados por apellido. Sin coincidencias responde **200** con `data: []` (la interfaz muestra «No hay docentes de esta materia que coincidan con la búsqueda»). `ya_invitado` es `true` si ya tiene una invitación PENDIENTE o ACEPTADA a ese examen (la interfaz no ofrece invitarlo otra vez).

```json
{ "data": [ { "id_usuario": "0000…0102", "nombre_completo": "Rolando Vega Méndez", "cod_sis": "10602", "ya_invitado": false } ] }
```

## POST /api/docente/invitaciones/examenes/{id_examen}

| Campo | Regla |
|---|---|
| `id_docente` | Obligatorio, uuid del docente a invitar. Un docente que no existe responde **404**; uno que no es invitable (sin grupos de la materia en el período vigente, inactivo, sin rol docente, o el propio docente) responde **422** en `errors.id_docente`. |
| `permite_agregar_grupos` | Opcional, booleano; por omisión `true`. Si el colaborador, al aceptar, puede sumar **sus propios grupos** de la materia al examen para unir las listas del control de ingreso. HU-026 solo lo guarda y lo muestra: **HU-027 lo hace cumplir**. |
| `lista_por_grupo` | Opcional, booleano; por omisión `false`. Si la lista del examen se muestra **dividida por grupo** (con su docente) en el control de ingreso y durante el examen, en lugar de junta. Solo con `permite_agregar_grupos: true`; si no, **422** en `lista_por_grupo` («Solo puede separar la lista por grupo si permite agregar grupos.»). En la pantalla es un segundo checkbox que se habilita al marcar «permitir agregar grupos». |

Responde **201** con la invitación (mismo formato que la lista de enviadas) y `mensaje: "Invitación enviada correctamente."`. Todo ocurre en una transacción que **bloquea la fila del examen**, así que dos envíos simultáneos se ordenan y un envío no se cruza con la apertura del control de ingreso; el índice único parcial de la base es el respaldo (también responde 409). Queda registrada en la bitácora la acción `INVITAR_COLABORADOR` sobre `invitacion_examen`, con el docente que invita, la fecha y la hora, y los datos de la invitación.

## GET /api/docente/invitaciones

Las invitaciones que **envió** el docente, de todos sus exámenes, de la más reciente a la más antigua. Sin invitaciones responde **200** con `data: []`.

```json
{
  "data": [
    {
      "id_invitacion": 9970,
      "estado": "PENDIENTE",
      "fecha_invitacion": "2026-11-13T13:00:00+00:00",
      "fecha_respuesta": null,
      "fecha_sin_efecto": null,
      "permite_agregar_grupos": true,
      "lista_por_grupo": false,
      "examen": {
        "id_examen": 9811, "nombre_examen": "HU-026 Parcial Cálculo I", "materia": "Cálculo I",
        "carrera": "Ingeniería de Sistemas", "fecha": "2026-12-10", "hora_inicio": "08:00", "hora_fin": "09:30",
        "estado": "PROGRAMADO"
      },
      "docente": { "id_usuario": "0000…0012", "nombre_completo": "Rosario Salazar Vidal", "cod_sis": "10487" }
    }
  ]
}
```

`estado` es uno de `PENDIENTE`, `ACEPTADA`, `RECHAZADA` o `SIN_EFECTO` (la interfaz los muestra como Pendiente, Aceptada, Rechazada y Sin efecto). Una invitación PENDIENTE cuyo examen ya no está PROGRAMADO se muestra `SIN_EFECTO` aunque todavía no se haya escrito así (lectura derivada; `fecha_sin_efecto` queda `null` en ese caso).

## «Sin efecto»

Una invitación PENDIENTE solo se puede responder mientras el examen está PROGRAMADO. Cuando el examen **sale de PROGRAMADO**, sus pendientes pasan a `SIN_EFECTO` con `fecha_sin_efecto`:

| Momento | Dónde se escribe |
|---|---|
| Se abre el control de ingreso | `RoomAssignmentService::prepare` |
| Se cancela el examen | `ExamService::cancel` |
| El examen termina (incluido el cierre automático de uno que quedó sin iniciar) | `ExamLifecycleService::finishLockedExam` |

En los tres casos llama a `ExamInvitationExpiryService::voidPendingForExam($idExamen)` dentro de la transacción que ya tiene bloqueado el examen. Solo toca las PENDIENTES: las aceptadas y rechazadas se conservan. Repetirlo no cambia nada. Si la apertura del ingreso falla, la transacción se revierte y las invitaciones siguen PENDIENTES.

## Contrato para HU-027

HU-026 **posee** la escritura de `SIN_EFECTO`, el enum, el CHECK, el índice y las columnas de abajo; HU-027 **no debe volver a agregarlos** y solo agrega lo suyo.

**Tabla `invitacion_examen`** (`App\Models\ExamInvitation`):

| Columna | Tipo | Notas |
|---|---|---|
| `id_invitacion`, `id_examen`, `id_docente_creador`, `id_docente_invitado`, `fecha_invitacion` | — | Ya existían. Nunca se modifican tras el envío. |
| `estado` | enum `estado_invitacion` | `PENDIENTE`, `ACEPTADA`, `RECHAZADA` y **`SIN_EFECTO`** (nuevo). Constantes en `ExamInvitation`. |
| `fecha_respuesta` | timestamptz | La escribe HU-027 al aceptar o rechazar. |
| **`permite_agregar_grupos`** | boolean, NOT NULL, por omisión `true` | **Nuevo.** Si el colaborador puede sumar sus grupos al aceptar. **HU-027 debe respetarlo:** con `false`, aceptar no admite grupos. |
| **`lista_por_grupo`** | boolean, NOT NULL, por omisión `false` | **Versión 18.** Lista dividida por grupo en el control de ingreso y durante el examen. CHECK `chk_invitacion_examen_lista_por_grupo`: solo `true` si `permite_agregar_grupos`. |
| `fecha_sin_efecto` | timestamptz | **Nuevo.** La escribe `ExamInvitationExpiryService`. |

**CHECK `chk_invitacion_examen_estado`** (cada estado fija qué fechas existen):

| Estado | `fecha_respuesta` | `fecha_sin_efecto` |
|---|---|---|
| `PENDIENTE` | vacía | vacía |
| `ACEPTADA` / `RECHAZADA` | **obligatoria** | vacía |
| `SIN_EFECTO` | vacía | **obligatoria** |

También: `chk_invitacion_examen_fechas` (la respuesta no es anterior al envío) y `chk_invitacion_examen_no_autoinvitacion` (creador ≠ invitado). El índice único parcial `uq_invitacion_examen_vigente (id_examen, id_docente_invitado) WHERE estado IN ('PENDIENTE','ACEPTADA')` impide dos invitaciones vigentes; si HU-027 pasa una invitación de PENDIENTE a otro estado, no lo viola.

**Reglas que HU-027 debe cumplir al responder:**

1. Solo se responde una invitación **PENDIENTE**; otra responde **409**.
2. Solo mientras el examen esté **PROGRAMADO**; si no, **409**.
3. Bloquear la fila del examen con `lockForUpdate` antes de cambiar la invitación, como hace HU-026: así una respuesta no se cruza con la apertura del control de ingreso (que bloquea la misma fila y deja la invitación SIN_EFECTO).
4. Aceptar con grupos solo si `permite_agregar_grupos` es `true`; los grupos deben ser del invitado, ACTIVOS, de la materia del examen y del período vigente (la misma regla que `ExamGroupService`).
5. Al aceptar o rechazar, escribir `fecha_respuesta` y dejar `fecha_sin_efecto` vacía.

**Columnas que HU-026 no crea:** `mensaje` y `motivo_rechazo` (que la maqueta de HU-027 menciona). Si las necesita, las agrega HU-027 con su propia migración.

**Acciones de bitácora:** HU-026 registra `INVITAR_COLABORADOR` (tabla `invitacion_examen`, tipo INSERT, etiqueta «Invitar colaborador»). Las acciones de aceptar y rechazar las define HU-027, en `ActionSeeder`, en una migración idempotente y en `config/sciem.php` (`bitacora.etiquetas_accion`); `AuditLogOptionsTest` exige que toda acción del catálogo tenga etiqueta.

**Rutas, carpetas y nombres ya ocupados por HU-026:** API `/api/docente/invitaciones/*`; Service `CollaboratorInvitationService` y `ExamInvitationExpiryService`; feature del cliente `features/collaborators` y ruta `/colaboradores/invitar`. HU-027 conserva `/api/invitaciones`, `features/invitations` y `/invitaciones`.

## Migraciones (orden y despliegue)

Dos migraciones, **en este orden**:

1. `2026_11_16_000000_add_sin_efecto_to_estado_invitacion`: `ALTER TYPE public.estado_invitacion ADD VALUE IF NOT EXISTS 'SIN_EFECTO'`. Va **fuera de transacción** (`$withinTransaction = false`), porque PostgreSQL no usa un valor de enum nuevo dentro de la misma transacción.
2. `2026_11_16_000001_prepare_invitacion_examen_for_collaborators`: las dos columnas, el CHECK nuevo, el CHECK de no auto-invitación, el índice único parcial y la acción `INVITAR_COLABORADOR`. Es **idempotente**: repetirla no cambia nada. Su `down()` aborta sin tocar nada si ya hay invitaciones o filas de bitácora de la acción.

`php artisan migrate --force` las aplica en ese orden. El mismo SQL está al final de `docs/database/creation-script.sql` (versión 15).
