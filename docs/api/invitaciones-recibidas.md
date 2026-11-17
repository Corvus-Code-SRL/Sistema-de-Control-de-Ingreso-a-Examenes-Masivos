# API de invitaciones recibidas (HU-027)

El docente **invitado** ve las invitaciones que le enviaron (HU-026) y las **acepta** o **rechaza**. Al aceptar puede sumar sus propios grupos de la materia al examen, si la invitación lo permite, y desde ese momento es **docente colaborador** del examen.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `mensaje` al responder); los errores llevan `message` (y `errors` por campo en los 422). Middlewares: `auth:sanctum`, `account.active`, `password.changed`.

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/invitaciones?estado=pendientes\|aceptadas\|cerradas` | Bandeja: invitaciones recibidas de una pestaña, con el total de cada pestaña |
| GET | `/api/invitaciones/{id_invitacion}` | Detalle: examen, ambientes, grupos del examen y grupos propios |
| POST | `/api/invitaciones/{id_invitacion}/aceptar` | Acepta, sumando los grupos elegidos |
| POST | `/api/invitaciones/{id_invitacion}/rechazar` | Rechaza (sin motivo) |

`{id_invitacion}` es de 1 a 9 dígitos; otro formato, una invitación que no existe o una **dirigida a otro docente** responde **404** («No existe la invitación indicada.»), para no revelar invitaciones ajenas. Las rutas de HU-026 (`/api/docente/invitaciones/*`) son las del docente que invita.

## Quién puede usarla

Una cuenta **activa** con rol **Docente o Invitado** (función `teaching` de `config/sciem.php`, los mismos roles a los que HU-026 puede invitar). En el cliente, la capacidad `examenes.gestionar`. Un Auxiliar, un Administrador o una cuenta sin rol recibe **403** («Solo un docente puede consultar y responder invitaciones a exámenes.»); sin token, **401**.

## Estado que se muestra y pestañas

`estado` es el que ve el docente (`ExamInvitation::effectiveState()`): una invitación **PENDIENTE** cuyo examen ya **abrió el control de ingreso** (`examen.ingreso_abierto_en`, HU-038: el examen sigue PROGRAMADO) o ya no está **PROGRAMADO** se muestra **SIN_EFECTO** aunque el ciclo de vida todavía no la haya escrito así.

| Pestaña | Contiene |
|---|---|
| `pendientes` (por defecto) | PENDIENTE con el examen PROGRAMADO y el control de ingreso sin abrir |
| `aceptadas` | ACEPTADA |
| `cerradas` | RECHAZADA, SIN_EFECTO y la PENDIENTE cuyo examen ya abrió el control o salió de PROGRAMADO |

Otro valor de `estado` responde **422**. El orden es de la más reciente a la más antigua.

## GET /api/invitaciones

```json
{
  "data": [
    {
      "id_invitacion": 9974,
      "estado": "PENDIENTE",
      "fecha_invitacion": "2026-11-14T13:00:00+00:00",
      "fecha_respuesta": null,
      "fecha_sin_efecto": null,
      "permite_agregar_grupos": true,
      "lista_por_grupo": false,
      "examen": {
        "id_examen": 9821,
        "nombre_examen": "HU-027 Parcial aula chica",
        "materia": "Cálculo I",
        "carrera": "Ingeniería de Sistemas",
        "periodo": null,
        "fecha": "2026-12-14",
        "hora_inicio": "08:00",
        "hora_fin": "09:30"
      },
      "invitante": { "nombre_completo": "Marcelo Quiroga Andrade" }
    }
  ],
  "meta": { "total_pendientes": 2, "total_aceptadas": 1, "total_cerradas": 2 }
}
```

Las fechas de la invitación van en UTC con desfase (ISO 8601); la interfaz las muestra en hora de La Paz. La fecha y las horas del examen son locales. En la bandeja `examen.periodo` es `null`: solo el detalle lo calcula.

## GET /api/invitaciones/{id_invitacion}

Lo mismo que una fila de la bandeja, más:

```json
{
  "data": {
    "…": "campos de la bandeja",
    "examen": { "…": "…", "periodo": "2/2026" },
    "ambientes": [ { "id_ambiente": 9607, "nro_aula": "QA-8", "capacidad": 8 } ],
    "grupos_examen": [ { "id_grupo": 9401, "num_grupo": "1", "inscritos": 7 } ],
    "mis_grupos": [
      { "id_grupo": 9402, "num_grupo": "2", "inscritos": 5, "seleccionable": true, "motivo": null, "sumado": false }
    ]
  }
}
```

- `examen.periodo`: período de los grupos del examen («2/2026»); `null` si el examen aún no tiene grupos.
- `mis_grupos`: grupos del invitado de la **materia del examen**, **ACTIVOS** y del **período vigente**. `seleccionable` es `false` si ya está en el examen (`sumado: true`) o no tiene nómina (`motivo: "sin nómina"`).
- La capacidad no bloquea: la interfaz suma `inscritos` y compara con la capacidad de `ambientes` para avisar.
- La invitación **no guarda mensaje ni motivo de rechazo**: `invitacion_examen` no tiene esas columnas.

## POST /api/invitaciones/{id_invitacion}/aceptar

```json
{ "grupos": [9402] }
```

`grupos` es opcional y puede ir vacío: se acepta sin sumar grupos. Responde **200** con el detalle actualizado y `mensaje: "Invitación aceptada."`.

Reglas, en este orden y dentro de una transacción:

1. Se bloquea la fila del **examen** (`FOR UPDATE`, como la apertura del control de ingreso y el envío de invitaciones) y luego la de la invitación.
2. La invitación debe estar **PENDIENTE**; si no, **409** («La invitación ya fue respondida o quedó sin efecto.»).
3. El examen debe estar **PROGRAMADO** y **sin el control de ingreso abierto** (`ingreso_abierto_en` nulo); si no, **409** («La invitación ya no se puede responder: el examen ya abrió el control de ingreso o no está programado.»).
4. Si `permite_agregar_grupos` es `false` y llegan grupos, **422** en `grupos` («Esta invitación no permite agregar grupos al examen.»).
5. Los grupos se validan con la regla de `ExamGroupService` (**422** en `grupos`): propios del invitado, ACTIVOS, del período vigente y de la materia del examen; con nómina («El grupo N no tiene nómina cargada.»); y sin estudiantes repetidos con los grupos que el examen ya tenía («Un estudiante figura en más de uno de los grupos seleccionados.»).
6. Se vinculan los grupos (`grupo_examen`), la invitación pasa a **ACEPTADA** con `fecha_respuesta` y `fecha_sin_efecto` queda `null`.
7. Bitácora: `ACEPTAR_INVITACION` sobre `invitacion_examen`, con el estado anterior y los `grupos_sumados`, firmada por el invitado.

Si cualquier paso falla no cambia nada.

## POST /api/invitaciones/{id_invitacion}/rechazar

Sin cuerpo. Mismos pasos 1 a 3; la invitación pasa a **RECHAZADA** con `fecha_respuesta`, el examen no cambia y se registra `RECHAZAR_INVITACION`. Responde **200** con `mensaje: "Invitación rechazada."`.

## Lista junta o dividida por grupo

Quien invita elige en HU-026 (`lista_por_grupo`, segundo checkbox, solo si permite agregar grupos). La **nómina es la misma** en los dos casos: los grupos sumados se vinculan al examen y sus estudiantes entran en la misma lista. Solo cambia cómo se muestra, y lo decide `ExamCollaboratorService::listsByGroup()`: basta una invitación **ACEPTADA** con `lista_por_grupo: true`.

| Dónde | Lista junta (por defecto) | Lista dividida por grupo |
|---|---|---|
| `GET /api/control-ingreso/examenes/{id}/estado` (032) | `ingresados`, `pendientes`, `total` | Además `por_grupo: [{id_grupo, num_grupo, docente, total, ingresados}]`; cada fila de `ultimos_ingresos` trae `id_grupo` |
| `POST …/verificar` (032) | `grupo: {id_grupo, num_grupo, carrera}` | `grupo` trae además `docente` |
| `GET /api/examenes/{id}/en-curso` (038) | `lista_por_grupo: false` | `lista_por_grupo: true`; la vista agrupa `estudiantes` por `id_grupo` usando `grupos: [{id_grupo, num_grupo, docente}]` |

El conteo por grupo se arma al abrir el control de ingreso (el snapshot); como solo se acepta antes de que se abra el control, no cambia después.

## Qué puede hacer el colaborador

El creador **administra** el examen y el colaborador lo **controla**. Un colaborador es la invitación **ACEPTADA** (no hay otra tabla; ver `ExamCollaboratorService`). Es docente del examen en:

| HU | Qué |
|---|---|
| 032 | Ve el examen en el control de ingreso con `rol_controlador: DOCENTE` y controla todos los ambientes; confirma y rechaza ingresos |
| 037 | Solicitudes y registro de ingreso con retraso, como docente |
| 038 | Vista del examen en curso |
| 042 / 057 | Incidencias y situaciones del examen |
| 051 | Reporte final del examen finalizado |
| 052 | Historial: el examen aparece entre los suyos |

También ve el examen en **Programados** (`GET /api/examenes`, con `?vista=programados` o sin ella) y su detalle (`GET /api/examenes/{id}`), **solo para leer**: la interfaz lo marca con la insignia «Colaborador». El examen donde solo colabora **no** aparece en el desplegable de HU-026 (`GET /api/docente/invitaciones/examenes`): no puede invitar a otros.

### `es_creador`

Los recursos que alimentan la interfaz traen `es_creador` (`true` solo para el docente que creó el examen): el listado y el detalle de `/api/examenes`, `GET /api/control-ingreso/examenes`, `GET /api/control-ingreso/examenes/{id}/contexto` y `GET /api/examenes/{id}/en-curso`. Con `es_creador: false` la interfaz oculta lo que el servidor responde con **403**; `rol_controlador` sigue diciendo si se controla como docente o como auxiliar y ya no se usa para saber quién creó el examen.

### Solo del creador (403 para un colaborador)

| Acción | Ruta |
|---|---|
| Modificar el examen | `PUT /api/examenes/{id}` |
| Cancelar | `POST /api/examenes/{id}/cancelar` |
| Asignar grupos al examen | `POST /api/examenes/{id}/grupos` |
| Cerrar el ingreso ordinario | `POST /api/control-ingreso/examenes/{id}/cerrar-ingreso-ordinario` |
| Terminar el examen | `POST /api/examenes/{id}/finalizar` |
| Lista de habilitados (028) y habilitar auxiliares (029/030) | `/api/examenes/{id}/habilitados`, `/api/examenes/{id}/auxiliares` |
| Revisar los reportes de falta (045) | `/api/docente/incidencias` |
| Invitar docentes (HU-026) | `/api/docente/invitaciones/*` |

## Decisiones del PR

- **Invitado (temporal).** El rol «Docente invitado (temporal)» puede recibir y responder invitaciones (función `teaching`); solo el rol Docente puede invitar (`invite_teachers`).
- **Invitación ajena.** Una invitación dirigida a otro docente responde 404, no 403, para no revelar que existe.
- **Lista por grupo.** Si **alguna** invitación aceptada del examen tiene `lista_por_grupo: true`, todo el examen usa la lista por grupo, también para los colaboradores que aceptaron una invitación sin esa opción.

## Datos de QA

`Sprint5QaSeeder`: Rosario Salazar (10487) tiene invitaciones en los cuatro estados; la del examen 9821 («HU-027 Parcial aula chica», aula QA-8 de 8 lugares) sirve para ver el aviso de capacidad al marcar su grupo 2. Ver el comentario del seeder.
