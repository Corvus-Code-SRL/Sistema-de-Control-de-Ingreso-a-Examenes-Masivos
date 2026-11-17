# Distribución de habilitados por ambiente (HU-031)

Contrato de HU-031, con vista del Docente e integración de lectura en el control de ingreso.

## Acceso y formato

Todas las rutas están bajo `/api` y requieren sesión, cuenta activa y acceso a `teaching`
(Docente o Invitado según `config('sciem.access')`). Se usa `Accept: application/json`;
el PUT recibe `Content-Type: application/json`.

El creador del examen puede proponer y guardar. Un colaborador con invitación ACEPTADA solo
consulta. El selector devuelve los exámenes PROGRAMADO de ambos tipos; un control ya abierto
permanece visible, con `editable: false`.

| Método | Ruta | HTTP de éxito | Cuerpo |
|---|---|---|---|
| GET | `/api/examenes/distribucion` | 200 | `{ data: ExamOption[] }` |
| GET | `/api/examenes/{exam}/distribucion` | 200 | `{ data: DistributionView }` |
| POST | `/api/examenes/{exam}/distribucion/propuesta` | 200 | `{ data: DistributionProposal }` |
| PUT | `/api/examenes/{exam}/distribucion` | 200 | `{ data: DistributionView, mensaje: string }` |

**`mensaje` solo se incluye en un PUT exitoso**, con el valor «Distribución guardada».
Los GET y el POST de propuesta omiten esa clave; no la devuelven como `null`.
Los errores usan `message`, en singular y en inglés como nombre de clave, según el contrato compartido de SCIEM.

Los ejemplos siguientes fueron capturados de los endpoints en `postgres/sciem_test`, con datos
sintéticos y depuración desactivada solo para el proceso de captura. Los identificadores y
versiones son valores de ese escenario de prueba, no identificadores estables del catálogo.

## Tipos y campos anulables

`integer` significa un número entero JSON; `boolean` es `true` o `false`.
Las colecciones son arrays, incluso cuando están vacías. Los campos indicados se incluyen siempre;
solo aceptan `null` los marcados expresamente como anulables.

### ExamOption

| Campo | Tipo | Significado |
|---|---|---|
| `id_examen` | integer | Identificador del examen |
| `nombre` | string | Nombre del examen |
| `materia` | objeto `{ nombre: string }` o null | Materia; puede ser null en un examen sin materia |
| `fecha` | string | Fecha en formato `YYYY-MM-DD` |
| `hora_inicio` | string | Hora en formato `HH:mm:ss` |
| `cantidad_ambientes` | integer | Cantidad de ambientes actuales |
| `es_creador` | boolean | El usuario autenticado creó el examen |
| `editable` | boolean | Es creador, la ventana está abierta y existen al menos dos ambientes |

`editable` describe permisos y ventana; una capacidad insuficiente no lo transforma en `false`.
El cliente debe considerar también `faltan_asientos` y los estudiantes sin asignación al guardar.

### DistributionView

| Campo | Tipo | Significado |
|---|---|---|
| `examen` | ExamOption | Examen seleccionado; mismo tipo del selector |
| `editable` | boolean | Posibilidad de editar esta planificación |
| `motivo_solo_lectura` | string o null | Motivo cuando `editable` es false; null cuando es true |
| `version` | string | SHA-1 de las filas guardadas; 40 caracteres hexadecimales minúsculos |
| `total_habilitados` | integer | Estudiantes únicos HABILITADO o CASO ESPECIAL |
| `total_no_habilitados` | integer | Estudiantes únicos con estado explícito NO_HABILITADO |
| `capacidad_total` | integer | Suma de las capacidades de los ambientes actuales |
| `faltan_asientos` | integer | `max(0, total_habilitados - capacidad_total)` |
| `ambientes` | Ambiente[] | Ambientes por `id_ambiente` ascendente |
| `estudiantes` | Estudiante[] | Solo habilitados, sin duplicados y en orden alfabético español |

Los dos totales reutilizan `ExamRosterService::expected()`: pertenecer a varios grupos no multiplica
al estudiante. Sin fila en `examen_estudiante`, el estudiante cuenta como HABILITADO por defecto.
En FINALIZADO se usa la nómina congelada que define ese mismo servicio.

### Ambiente y Estudiante

| Campo de Ambiente | Tipo | Significado |
|---|---|---|
| `id_ambiente` | integer | Identificador del ambiente |
| `nro_aula` | string | Número o nombre del aula |
| `capacidad` | integer | Capacidad física |
| `asignados` | integer | Asignaciones vigentes en la vista; no supera capacidad |

| Campo de Estudiante | Tipo | Significado |
|---|---|---|
| `id_estudiante` | integer | Identificador único del estudiante |
| `codigo_sis` | string | Código SIS; se mantienen los ceros iniciales |
| `nombre_completo` | string | Apellido paterno, materno si existe y nombres |
| `id_ambiente` | integer o null | Ambiente planificado; null significa «Sin ambiente asignado» |

El orden usa apellido paterno, materno, nombres y código SIS. Se ignoran mayúsculas y tildes;
la ñ se ordena después de la n, sin depender de `intl`.

### DistributionProposal y cuerpo del PUT

| Campo de DistributionProposal | Tipo | Significado |
|---|---|---|
| `estudiantes` | Estudiante[] | Propuesta en orden alfabético; usa el mismo tipo anterior |
| `sin_asignar` | integer | Cantidad con `id_ambiente: null` en esa propuesta |

| Campo del PUT | Tipo | Restricción |
|---|---|---|
| `version` | string | Obligatorio; SHA-1 con el formato de DistributionView |
| `asignaciones` | array de Asignacion | Obligatorio, incluso cuando es `[]` |
| `asignaciones[].id_estudiante` | integer | Obligatorio; 1..2147483647 y sin repetirse |
| `asignaciones[].id_ambiente` | integer | Obligatorio; 1..2147483647; no admite null |

Los identificadores enviados como texto numérico también se validan y normalizan, pero se
recomienda enviarlos como números JSON. El PUT debe incluir exactamente una asignación por
habilitado actual, con ambientes del propio examen y sin exceder capacidad. No acepta
NO_HABILITADO, estudiantes ajenos, omisiones ni una lista parcial de cambios.

## GET /api/examenes/distribucion

No tiene cuerpo ni necesita pasar por el detalle del examen. Un usuario sin exámenes Programados
recibe `{ "data": [] }`. Respuesta real del escenario:

```json
{
  "data": [
    {
      "id_examen": 1,
      "nombre": "Parcial de Cálculo",
      "materia": {
        "nombre": "Calculo II"
      },
      "fecha": "2026-12-01",
      "hora_inicio": "08:00:00",
      "cantidad_ambientes": 2,
      "es_creador": true,
      "editable": true
    }
  ]
}
```

## GET /api/examenes/{exam}/distribucion

Ejemplo: `GET /api/examenes/1/distribucion`, antes de confirmar la distribución.
El escenario tiene un HABILITADO explícito, un NO_HABILITADO, un CASO ESPECIAL y un habilitado
por defecto sin fila en `examen_estudiante`.

```json
{
  "data": {
    "examen": {
      "id_examen": 1,
      "nombre": "Parcial de Cálculo",
      "materia": {
        "nombre": "Calculo II"
      },
      "fecha": "2026-12-01",
      "hora_inicio": "08:00:00",
      "cantidad_ambientes": 2,
      "es_creador": true,
      "editable": true
    },
    "editable": true,
    "motivo_solo_lectura": null,
    "version": "97d170e1550eee4afc0af065b78cda302a97674c",
    "total_habilitados": 3,
    "total_no_habilitados": 1,
    "capacidad_total": 60,
    "faltan_asientos": 0,
    "ambientes": [
      {
        "id_ambiente": 1,
        "nro_aula": "691A",
        "capacidad": 30,
        "asignados": 0
      },
      {
        "id_ambiente": 2,
        "nro_aula": "692B",
        "capacidad": 30,
        "asignados": 0
      }
    ],
    "estudiantes": [
      {
        "id_estudiante": 3,
        "codigo_sis": "200007001",
        "nombre_completo": "Álvarez Ana",
        "id_ambiente": null
      },
      {
        "id_estudiante": 5,
        "codigo_sis": "200007003",
        "nombre_completo": "Muñoz José",
        "id_ambiente": null
      },
      {
        "id_estudiante": 6,
        "codigo_sis": "200007004",
        "nombre_completo": "Zapata María",
        "id_ambiente": null
      }
    ]
  }
}
```

La consulta no escribe. Con varios ambientes conserva solo las asignaciones guardadas que siguen
siendo válidas; estudiantes nuevos, ambientes retirados y excedentes tras reducir capacidad quedan
con `id_ambiente: null`. Un no habilitado sale de `estudiantes` y entra en `total_no_habilitados`;
sus filas planificadas se eliminan recién al confirmar el siguiente guardado.

Con un único ambiente se calcula la asignación en lectura, sin persistir planificación y respetando
su capacidad. La vista permanece en solo lectura. Para el creador, con ventana abierta:

- Si alcanza la capacidad: «Todos los habilitados quedan en el ambiente 691A.»
- Si hay 4 habilitados y capacidad 2: «El ambiente 691A tiene capacidad para 2 de 4 habilitados.
  Faltan 2 lugares.» Se muestran 2 asignados, 2 sin ambiente y `faltan_asientos: 2`.

Un colaborador o un examen con ventana cerrada conserva el motivo de solo lectura correspondiente
a su permiso o estado, además de los contadores de capacidad.

## POST /api/examenes/{exam}/distribucion/propuesta

Ejemplo: `POST /api/examenes/1/distribucion/propuesta`. No requiere cuerpo.
Ordena alfabéticamente y reparte bloques consecutivos en proporción a la capacidad de cada
ambiente, por mayores restos y desempate por orden de ambiente. Por ejemplo, 55 habilitados
y dos ambientes de 30 producen 28 y 27; capacidades 40/20/20 con 60 producen 30/15/15.

Si falta capacidad, el excedente queda con `id_ambiente: null` y se cuenta en `sin_asignar`.
El POST no guarda ni genera bitácora. Respuesta real:

```json
{
  "data": {
    "estudiantes": [
      {
        "id_estudiante": 3,
        "codigo_sis": "200007001",
        "nombre_completo": "Álvarez Ana",
        "id_ambiente": 1
      },
      {
        "id_estudiante": 5,
        "codigo_sis": "200007003",
        "nombre_completo": "Muñoz José",
        "id_ambiente": 1
      },
      {
        "id_estudiante": 6,
        "codigo_sis": "200007004",
        "nombre_completo": "Zapata María",
        "id_ambiente": 2
      }
    ],
    "sin_asignar": 0
  }
}
```

La propuesta es un borrador: no reserva lugares ni congela sus fuentes después de devolver la
respuesta. El PUT siempre vuelve a comprobar los datos actuales.

## PUT /api/examenes/{exam}/distribucion

Ejemplo: `PUT /api/examenes/1/distribucion`. Cuerpo real, usando la versión y propuesta anteriores:

```json
{
  "version": "97d170e1550eee4afc0af065b78cda302a97674c",
  "asignaciones": [
    {
      "id_estudiante": 3,
      "id_ambiente": 1
    },
    {
      "id_estudiante": 5,
      "id_ambiente": 1
    },
    {
      "id_estudiante": 6,
      "id_ambiente": 2
    }
  ]
}
```

Confirma la lista completa en una sola transacción. Respuesta real:

```json
{
  "data": {
    "examen": {
      "id_examen": 1,
      "nombre": "Parcial de Cálculo",
      "materia": {
        "nombre": "Calculo II"
      },
      "fecha": "2026-12-01",
      "hora_inicio": "08:00:00",
      "cantidad_ambientes": 2,
      "es_creador": true,
      "editable": true
    },
    "editable": true,
    "motivo_solo_lectura": null,
    "version": "f7eaf845e782bb110d0696162f07f3a0fe1b12e9",
    "total_habilitados": 3,
    "total_no_habilitados": 1,
    "capacidad_total": 60,
    "faltan_asientos": 0,
    "ambientes": [
      {
        "id_ambiente": 1,
        "nro_aula": "691A",
        "capacidad": 30,
        "asignados": 2
      },
      {
        "id_ambiente": 2,
        "nro_aula": "692B",
        "capacidad": 30,
        "asignados": 1
      }
    ],
    "estudiantes": [
      {
        "id_estudiante": 3,
        "codigo_sis": "200007001",
        "nombre_completo": "Álvarez Ana",
        "id_ambiente": 1
      },
      {
        "id_estudiante": 5,
        "codigo_sis": "200007003",
        "nombre_completo": "Muñoz José",
        "id_ambiente": 1
      },
      {
        "id_estudiante": 6,
        "codigo_sis": "200007004",
        "nombre_completo": "Zapata María",
        "id_ambiente": 2
      }
    ]
  },
  "mensaje": "Distribución guardada"
}
```

La respuesta incluye nuevamente DistributionView, con la versión confirmada y ocupación
actualizada. Salir o volver a pedir una propuesta sin ejecutar PUT conserva la planificación anterior.

## Errores

Sin depuración, el cuerpo tiene `message: string`. Los errores de validación 422 agregan
`errors: { [campo: string]: string[] }`. Ninguno incluye `mensaje` ni una distribución parcial
en `data`. El cliente debe usar el código HTTP y `errors`; no debe interpretar textos humanos
como códigos estables.

| HTTP | Situación |
|---|---|
| 401 | Falta sesión |
| 403 | Rol no permitido, examen ajeno o propuesta/guardado de un colaborador |
| 404 | Examen inexistente o identificador que no cumple la ruta numérica |
| 409 | Ventana cerrada o versión obsoleta |
| 422 | Formato inválido, menos de dos ambientes, nómina incompleta o capacidad insuficiente |
| 500 | Fallo inesperado; el reemplazo y la bitácora se revierten juntos |

Los middlewares comunes de SCIEM pueden rechazar también una cuenta inactiva, una contraseña
temporal pendiente o un exceso de solicitudes. Esas reglas no cambian en HU-031.

### 401 — sesión ausente

```json
{
  "message": "Unauthenticated."
}
```

### 403 — examen ajeno

Se reutiliza `ExamOwnershipException`, incluido su mensaje compartido:

```json
{
  "message": "Solo el docente que creó el examen puede modificarlo o cancelarlo."
}
```

### 404 — examen inexistente

```json
{
  "message": "No query results for model [App\\Models\\Exam] 999999999"
}
```

### 409 — versión obsoleta

```json
{
  "message": "La distribución cambió. Recargue la distribución antes de guardar."
}
```

Se conserva la distribución confirmada. El cliente debe recargar y revisar antes de reintentar.

### 409 — ventana cerrada

```json
{
  "message": "El examen ya no permite cambios de preparación."
}
```

Ocurre cuando el examen dejó de estar PROGRAMADO o ya se abrió su control de ingreso.

### 422 — versión ausente o mal formada

El mensaje general es el de Laravel; los mensajes de campo se definen en español:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "version": [
      "Debe indicar una versión de distribución válida."
    ]
  }
}
```

### 422 — capacidad total insuficiente

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "asignaciones": [
      "Faltan 1 lugares para asignar a todos los habilitados."
    ]
  }
}
```

Los errores de nómina incompleta, ambiente ajeno y exceso por ambiente también usan
`errors.asignaciones: string[]`. Un ambiente lleno devuelve «El ambiente ya está lleno».
Una duplicación del estudiante señala `asignaciones.N.id_estudiante`; un ambiente nulo o
inválido señala `asignaciones.N.id_ambiente`, donde N es el índice del elemento enviado.

Con `APP_DEBUG=true` Laravel puede añadir metadatos de depuración. No forman parte del contrato
del cliente; en producción se usa `APP_DEBUG=false`.

## Guardado, bloqueos y trazabilidad

Para proponer o guardar se requieren creador, examen PROGRAMADO, control sin abrir y al menos
dos ambientes. La lectura admite otros estados y colaboradores aceptados.

El guardado toma, dentro de DB::transaction:

1. El examen con FOR UPDATE, para serializar apertura, edición, grupos y habilitaciones.
2. La cuenta docente con FOR SHARE; después revalida el rol y la propiedad. Este orden coincide
   con la edición de exámenes. SHARE bloquea los cambios de cuenta/rol que usan su protocolo y
   es compatible con las referencias FOR KEY SHARE de las claves foráneas de bitácora.
3. Ambientes por id ascendente con FOR SHARE, para conservar las capacidades leídas. Se toman
   antes de los grupos, como al crear un examen en HU-024; esto evita el cruce grupos → ambientes
   contra ambientes → grupos cuando otro docente comparte esos recursos.
4. Relaciones de examen_ambiente con FOR UPDATE.
5. Grupos por id ascendente, inscripciones por grupo/estudiante y filas de examen_estudiante
   con FOR UPDATE.
6. Filas de distribución del examen con FOR UPDATE.

Se mantienen los bloqueos de fuentes para el reemplazo completo. Se releen y validan versión,
nómina y aforo antes de borrar/insertar. No se aplica FOR UPDATE a la consulta agregada de
`ExamRosterService`: se bloquean las tablas que la alimentan.

La propuesta usa únicamente FOR SHARE sobre el examen durante su transacción: protege su
ventana frente a una apertura o edición concurrente, admite otras propuestas y no toma los
bloqueos exclusivos de las fuentes o filas planificadas. Sus lecturas son un borrador revisable;
una modificación de nómina, capacidades o rol puede obligar a revisarlo al hacer PUT.

La clave primaria `(id_examen, id_estudiante)` se conserva. Las escrituras masivas se delimitan
explícitamente por examen; no se usa el `save()` de una instancia Eloquent. `asignado_en` se
persiste como cadena `Y-m-d H:i:s.uP`. Si el reloj no supera el guardado anterior, se avanza un
microsegundo para invalidar la versión previa incluso con el mismo contenido.

Cada confirmación genera una fila GUARDAR_DISTRIBUCION. `antiguo_valor.cambios` conserva los
ambientes previos; `nuevo_valor.cambios` contiene estudiante, SIS, ambiente anterior y nuevo.
Solo se listan los estudiantes que cambiaron, incluidos retiros de la planificación. Un guardado
sin cambios de asignación igualmente registra la confirmación, con `total_cambios: 0`.

## Decisión y limitación para cero habilitados

Con dos o más ambientes y ventana abierta se acepta `asignaciones: []`. Se eliminan filas
planificadas anteriores y se audita la confirmación, incluso si no había filas.
La propuesta devuelve `estudiantes: []` y `sin_asignar: 0`.

No se agrega un marcador: una distribución vacía comparte la versión inicial
`97d170e1550eee4afc0af065b78cda302a97674c` (`sha1('[]')`).
Por eso la versión sola no distingue «nunca confirmada» de «confirmada vacía», no avanza por
confirmaciones vacías repetidas y no detecta un ciclo vacía → no vacía → vacía. Las confirmaciones
y los cambios siguen en bitácora.

Esta limitación se conserva deliberadamente: no hay asignaciones vigentes que perder con otra
confirmación vacía. Si luego se habilitan estudiantes, aparecen sin ambiente y el PUT vuelve a
exigir la nómina actual completa, independientemente de que conserve la versión vacía.

## Integración con el control de ingreso (A3)

`RoomDistributionReader::assignedRooms(id_examen)` es una lectura sin escrituras ni bitácora
adicional. Lo consumen `EntryControlSnapshotService::stage()`, `recordFor()` (también usado
por ingreso con retraso) y `ExamInProgressQueryService`:

1. Sin filas en `distribucion_ambiente`, devuelve `null`. Los tres consumidores conservan
   su cálculo anterior por ID y capacidades. Una confirmación vacía también devuelve `null`,
   por la decisión de no agregar un marcador para cero habilitados.
2. Con filas guardadas, obtiene la nómina habilitada única y ordenada de
   `DistributionRosterQuery`, reutilizando `ExamRosterService` y `SurnameDistributor`.
3. Consulta directamente los `CASO ESPECIAL` de `examen_estudiante` con ambiente operativo.
   Sus ambientes prevalecen sobre el plan y reservan lugares antes de ubicar al resto.
4. `DistributionReconciler` conserva las asignaciones guardadas válidas respecto de la
   nómina, los ambientes actuales y su capacidad restante. Deshabilitar un estudiante libera
   su lugar; retirar un ambiente planificado deja su asignación pendiente de reubicación.
5. Solo los habilitados pendientes se distribuyen por apellido, proporcionalmente a los
   lugares que siguen libres. No se redistribuye toda la nómina ni se modifica la planificación.

El resultado es un mapa por estudiante con `{ id_ambiente: integer, nro_aula: string }`.
Un estudiante excedente no tiene entrada en el mapa. **Un mapa vacío no es `null`**: existe
planificación y no se activa el cálculo anterior, que podría reemplazar silenciosamente el plan.
GET de HU-031 sigue mostrando los nuevos habilitados sin ambiente hasta que el Docente guarde;
la ubicación operativa al abrir no escribe filas nuevas en `distribucion_ambiente` ni avanza su versión.

### Capacidad insuficiente después del guardado

La lectura conserva las asignaciones que caben, ubica pendientes solo en plazas libres y deja
el excedente con `ambiente_asignado: null` en el snapshot y `id_ambiente: null` / `ambiente: null`
en examen en curso. No lanza un nuevo error de apertura ni introduce otro bloqueo.

El contrato existente de verificación ya rechaza al estudiante sin ambiente con
`veredicto: "AULA_NO_ASIGNADA"`, `autorizado: false` y
`motivo: "No se pudo determinar un ambiente para el estudiante."`. La integración reutiliza
ese contrato sin agregar códigos ni cambiar la precedencia de otros motivos de rechazo.

**La apertura ya tenía una comprobación independiente:** `RoomAssignmentService::prepare()`
compara la capacidad con toda la nómina de grupos, incluidos NO_HABILITADO, y puede devolver
`CAPACIDAD_INSUFICIENTE` antes de generar el snapshot. Ese comportamiento se conserva.
Las pruebas de excedentes ejercitan `stage()` directamente para verificar el contrato de lectura;
no eliminan ni evaden esa comprobación en el flujo real de apertura.

Si una reducción de capacidad ya dejó más excepcionales con ambiente operativo que plazas,
se conservan esos ambientes por A3. El lector no agrega estudiantes a ese ambiente y limita
su capacidad disponible a cero; no traslada excepcionales ni corrige silenciosamente ese exceso
preexistente. Su resolución corresponde al responsable del ingreso y requiere una decisión aparte.

### HU-023 y ambientes operativos

En esta revisión del repositorio no hay un servicio de alta de HU-023. La representación
existente es `examen_estudiante.estado_habilitacion = 'CASO ESPECIAL'` y su `id_ambiente`;
hay fixtures y pruebas de habilitación/verificación para ese estado. El lector consulta esas
filas directamente, sin depender de que `ExamRosterService::expected()` las incluya.
Un CASO ESPECIAL sin ambiente operativo participa del plan como cualquier otro habilitado.

Las claves foráneas actuales exigen que `examen_estudiante` pertenezca a `grupo_examen` y
`grupo_estudiante`. Por eso un excepcional verdaderamente fuera de esos grupos no se puede
crear con el esquema vigente. La prueba de independencia de la nómina simula únicamente su
omisión en la consulta, manteniendo las FK activas. No se implementa un nuevo mecanismo de alta.
La FK `fk_ingreso_examen_ambiente` también impide retirar un ambiente operativo ya referenciado;
los casos de ambientes retirados se refieren a la planificación, que deliberadamente carece de esa FK.

En examen en curso sigue prevaleciendo el `id_ambiente` operativo de `examen_estudiante` sobre
la asignación calculada, preservando el comportamiento de ingreso y de situaciones excepcionales.
HU-031 no modifica el registro ni la sincronización de esos cambios de ambiente.

### Avisos de la vista

En edición y con capacidad suficiente se advierte que los pendientes se ubicarán al abrir
si siguen existiendo lugares. Con capacidad insuficiente no se promete su asignación.
En solo lectura el aviso se limita a consultar los casos con el docente responsable: no ofrece
ubicar ni guardar, ni promete una asignación futura cuando el control podría estar abierto.

## Hallazgos preexistentes fuera del alcance

- **A4, NO_HABILITADO:** `EntryControlSnapshotService::assignRooms()` no filtra habilitación.
  Sin distribución guardada sigue asignando ambientes por ID a toda la nómina, consumiendo
  plazas también para NO_HABILITADO. La verificación los rechaza posteriormente. No se cambia
  ese cálculo. Con una distribución HU-031 el lector sí usa únicamente habilitados, como exige CA-3.
- **Importación y apertura:** `StudentRosterConfirmationService` comprueba disponibilidad antes
  de la transacción; `StudentRosterConfirmer` bloquea el grupo, mientras `RoomAssignmentService`
  abre con el examen bloqueado sin bloquear sus grupos. Una importación ya autorizada puede
  completarse después de que la apertura leyó la nómina. La coordinación sigue pendiente de sus HU.
- El snapshot es operativo y se fija al abrir, mientras `recordFor()` y examen en curso consultan
  PostgreSQL. Una modificación externa de nómina o capacidades después de la apertura puede
  provocar divergencia; HU-031 no modifica los protocolos de otras HU ni reconstruye snapshots.

Escenario manual preparado, sin ejecutar: [QA local de HU-031](../qa/hu031-distribucion.md).
