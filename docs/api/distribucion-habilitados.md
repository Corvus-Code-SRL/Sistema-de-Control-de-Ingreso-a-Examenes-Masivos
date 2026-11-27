# Distribución de habilitados por ambiente (HU-031)

Contrato de HU-031, con vista del Docente e integración de lectura en el control de ingreso.

## Acceso y formato

Todas las rutas están bajo `/api` y requieren sesión, cuenta activa y la capacidad `room_distribution`
(Docente o Invitado según `config('sciem.access')`; en la interfaz, `examenes.distribuir-habilitados`). Se usa `Accept: application/json`;
el PUT recibe `Content-Type: application/json`.

El creador del examen puede proponer y guardar. Un colaborador con invitación ACEPTADA solo
consulta. El selector devuelve los exámenes PROGRAMADO de ambos tipos y los EN_CURSO con distribución
guardada; un control ya abierto permanece visible, con `editable: false`.

| Método | Ruta | HTTP de éxito | Cuerpo |
|---|---|---|---|
| GET | `/api/examenes/distribucion` | 200 | `{ data: ExamOption[] }` |
| GET | `/api/examenes/{exam}/distribucion` | 200 | `{ data: DistributionView }` |
| POST | `/api/examenes/{exam}/distribucion/propuesta` | 200 | `{ data: DistributionProposal }` |
| PUT | `/api/examenes/{exam}/distribucion` | 200 | `{ data: DistributionView, mensaje: string }` |
| POST | `/api/examenes/{exam}/distribucion/dejar-de-usar` | 200 | `{ data: DistributionView, mensaje: string }` |

**`mensaje` solo se incluye al guardar («Distribución guardada») o al dejar de usarla.**
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
Ordena alfabéticamente y reparte bloques consecutivos en partes iguales. Si un ambiente no admite
su parte, el excedente pasa a los que aún tienen lugar; el resto va uno a uno a los primeros y nunca
se supera la capacidad. Por ejemplo: 100 en 60/60 da 50/50; 100 en 80/40 da 60/40; 55 en 30/30 da 28/27.

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

Ocurre cuando el examen dejó de estar PROGRAMADO, ya llegó su hora de apertura (inicio menos
`minutos_apertura`, aunque el job aún no haya corrido) o ya se abrió su control de ingreso.

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

## Integración con el control de ingreso

La distribución es **opcional y avisa, no bloquea**. `RoomDistributionReader::assignedRooms()` es una
lectura pura: devuelve el ambiente planificado de cada habilitado y un mapa vacío si no hay distribución
o se dejó de usar. No reparte a nadie: un habilitado sin fila queda sin ambiente y entra por cualquiera.

`EntryRoomPolicy` es la regla común de HU-032 y HU-037:

| Caso | Resultado |
|---|---|
| Sin distribución, suspendida o estudiante sin ambiente | Registra en cualquier ambiente del examen, sin aviso |
| Ambiente distinto al planificado | `AUTORIZADO` con aviso `FUERA_DE_AMBIENTE` |
| Ambiente lleno (`INGRESO` o `CON_RETRASO`, sin retirados) | Aviso `AMBIENTE_LLENO` al registrar |
| Estudiante excepcional (HU-023) | No forma parte de la distribución; su ambiente asignado es el que se eligió al registrarlo y, si ingresa por otro, recibe el mismo aviso |

Verificar no consulta PostgreSQL, por lo que `avisos_ambiente` solo adelanta el ambiente distinto. Al
registrar se recalcula todo con el examen bloqueado. Si hay avisos sin confirmar responde:

```json
{
  "message": "Confirme el aviso de ambiente para registrar al estudiante.",
  "veredicto": "AVISO_AMBIENTE",
  "avisos_ambiente": [
    {
      "tipo": "FUERA_DE_AMBIENTE",
      "mensaje": "Este estudiante tiene asignado el ambiente 691A",
      "ambiente_asignado": { "id_ambiente": 1, "nro_aula": "691A" }
    }
  ]
}
```

con HTTP 409. Se confirma reenviando `confirmar_aviso_ambiente: true` en `confirmar-ingreso` o en
`retrasos/solicitudes`, `retrasos/solicitudes/{solicitud}/autorizar` y `retrasos/registrar`.
`examen_estudiante.id_ambiente` guarda el aula real; `id_ambiente_planificado` solo se llena si entró
fuera de su ambiente. La respuesta incluye `fuera_de_ambiente` y la bitácora registra
`INGRESO_CON_AVISO_AMBIENTE`. No se crea intento rechazado, incidencia ni registro en Central de Riesgo.
`AULA_INCORRECTA` y `AULA_NO_ASIGNADA` ya no se emiten; se conservan para leer el historial.

### Dejar de usar la distribución

`POST /api/examenes/{exam}/distribucion/dejar-de-usar` no recibe cuerpo. Solo el creador puede usarlo,
con distribución guardada y una vez cerrada la ventana de edición, en un examen PROGRAMADO o EN_CURSO.
Es irreversible, escribe `DEJAR_USAR_DISTRIBUCION` en la bitácora y responde 409 si no corresponde.

### Apertura

La capacidad ya no impide abrir el control: `RoomAssignmentService::prepare()` solo exige nómina y ambientes.

## Hallazgos preexistentes fuera del alcance

- **HU-032, CASO ESPECIAL:** `EntryConfirmationService` escribe `HABILITADO` al confirmar el ingreso y
  sobrescribe un `CASO ESPECIAL`. HU-037 no lo hace. Riesgo reportado al equipo; no se corrige aquí.
- **Importación y apertura:** `StudentRosterConfirmationService` comprueba disponibilidad antes
  de la transacción; `StudentRosterConfirmer` bloquea el grupo, mientras `RoomAssignmentService`
  abre con el examen bloqueado sin bloquear sus grupos. Una importación ya autorizada puede
  completarse después de que la apertura leyó la nómina. La coordinación sigue pendiente de sus HU.
- El snapshot es operativo y se fija al abrir, mientras `recordFor()` y examen en curso consultan
  PostgreSQL. Una modificación externa de nómina o capacidades después de la apertura puede
  provocar divergencia; HU-031 no modifica los protocolos de otras HU ni reconstruye snapshots.

Escenario manual preparado, sin ejecutar: [QA local de HU-031](../qa/hu031-distribucion.md).
