# API de rectificación de la Central de Riesgo (HU-062)

Permite **rectificar** un registro confirmado de la Central de Riesgo de estudiantes. Rectificar es **anular** el registro: se usa cuando fue aprobado por error (estudiante equivocado, reporte sin sustento, etc.) y el registro se **retira de la Central**. No se edita el tipo de falta ni la descripción.

Desde la revisión del Sprint 5 la rectificación tiene **doble confirmación**: el Administrador **solicita** la rectificación y la acepta o la rechaza el **docente que aprobó el reporte** (`reporte_estudiante.id_usuario_revisor`). El registro solo sale de la Central si ese docente acepta. Mientras tanto, y si la solicitud se rechaza o vence, el registro **sigue activo** en la Central.

La anulación es **lógica e irreversible**: la fila de `central_riesgo` no se borra, se marca con la fecha, el administrador y el motivo. No existe forma de reactivarla. Un registro rectificado **ya no cuenta como antecedente** en ninguna parte del sistema (ver «Dónde deja de contar» en [central-riesgo.md](central-riesgo.md)). El reporte original, su evidencia y su revisión no se modifican. Un registro con una solicitud pendiente, rechazada o vencida **sigue contando** en todas partes.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `meta` en la búsqueda y la bandeja); los errores llevan `message` (y `errors` por campo en los 422). Middlewares: `auth:sanctum`, `account.active`, `password.changed`.

## El flujo

1. **Administrador** (`/rectificar-registro`): elige un registro, escribe el motivo y confirma en dos pasos. Se crea una **solicitud** `PENDIENTE` para el docente aprobador; el registro **no** se retira. La tarjeta del registro muestra «Esperando confirmación del docente» con el nombre del docente, la fecha de la solicitud y la de vencimiento.
2. **Docente** (bandeja «Solicitudes de rectificación»): ve la solicitud y la **acepta** o la **rechaza**.
   - **Aceptar**: el registro se anula exactamente como en la rectificación directa (`rectificado_en`, `id_usuario_rectificador` = el administrador que la pidió, `motivo_rectificacion` = el motivo de ese administrador) y la solicitud queda `ACEPTADA`.
   - **Rechazar**: motivo obligatorio (10 a 500 caracteres) y, opcionalmente, una imagen de evidencia. La solicitud queda `RECHAZADA` y el registro sigue en la Central.
3. **Administrador**: ve el resultado en cada registro activo («Rectificación rechazada N veces», solicitudes vencidas aparte y el último rechazo con su motivo, fecha y evidencia). Tras un rechazo o un vencimiento puede enviar una **solicitud nueva** (con un motivo nuevo); las anteriores quedan como historial. No hay límite de solicitudes y el administrador **no puede cancelar** una pendiente.

### Vencimiento

Una solicitud **vence** a los `sciem.rectificacion.dias_vencimiento` días de enviada (7 por defecto). Una solicitud vencida pasa a `VENCIDA`: ya no se puede responder (409), el registro sigue en la Central, no se anula nada y **no deja fila en la bitácora** (no cuenta como rechazo).

- Una **tarea programada** (`expire-rectification-requests`, cada hora y sin solaparse, en `Console/Kernel.php`) las marca `VENCIDA`. No usa cola de trabajos.
- Como red de seguridad, **toda lectura** trata como `VENCIDA` a una `PENDIENTE` cuya fecha de vencimiento ya pasó, y antes de enviar o responder una solicitud se marcan las vencidas. El vencimiento se guarda al enviar (`vence_en`): cambiar el plazo configurado no altera las solicitudes ya enviadas.
- Las vencidas **no cuentan** en el badge de pendientes del docente.

### Docente aprobador inactivo

Una solicitud solo tiene sentido si el docente puede responderla. Si la cuenta del docente que aprobó el reporte **no está activa** o **ya no tiene rol docente** (Docente o Invitado):

- El Administrador **no puede enviarle una solicitud** (409).
- Pasados los `sciem.rectificacion.dias_docente_inactivo` días (180 por defecto, aproximadamente un semestre) puede **rectificar el registro directamente**, sin confirmación, con el mismo motivo obligatorio y la misma confirmación en dos pasos.
- Antes de eso responde 409: «El docente que aprobó este registro ya no está activo. Podrá rectificarlo sin su confirmación a partir del {dd/mm/aaaa}.»

**De dónde sale la fecha de inactividad.** Toda la lógica está en un solo lugar: `ApprovingTeacherStatusService`.

| Fuente (`fuente`) | Cuándo se usa | Fecha |
|---|---|---|
| `ROL_CERRADO` | El docente ya no tiene ningún rol docente abierto | El cierre (`usuario_rol.fecha_fin`) de su último rol docente |
| `PRIMERA_DETECCION` | No hay fecha de cierre: la cuenta no está `ACTIVO` pero su rol sigue abierto, o el reporte no tiene aprobador (datos antiguos) | La primera vez que un Administrador **intentó** enviarle una solicitud o rectificar uno de sus registros |

La primera detección se guarda en `docente_inactivo_deteccion` (una fila por cuenta o, sin aprobador, por registro) **fuera** de la transacción del intento, así que se conserva aunque ese intento responda 409, y no se mueve en los intentos siguientes: los N días son reales y no se pueden reiniciar. **Nunca** se usa la fecha en que el registro entró a la Central. Si el docente vuelve a estar activo, la detección se borra en el siguiente intento. Solo consultar la ficha del registro no guarda nada: mientras no haya un intento, `puede_rectificar_desde` es `null`.

> **Regla futura (no implementada).** Cuando exista la gestión de habilitar y deshabilitar cuentas, la fecha de inactividad saldrá **únicamente** de la fecha de deshabilitación de la cuenta, porque una cuenta puede conservar su rol estando deshabilitada. Entonces `ROL_CERRADO` y `PRIMERA_DETECCION` dejan de usarse y el cambio se hace solo en `ApprovingTeacherStatusService`. Hoy no hay flujo que deshabilite cuentas ni que escriba la acción `DESHABILITAR`, por eso no existe esa fecha.

## Rutas del Administrador

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/rectificacion-riesgo/estudiantes?q=` | Busca estudiantes por código SIS, carnet o nombre |
| GET | `/api/rectificacion-riesgo/estudiantes/{id_estudiante}/registros` | Registros activos (con su resumen de rectificación) y registros ya rectificados |
| GET | `/api/rectificacion-riesgo/registros/{id}` | Ficha completa de un registro **activo**, con su resumen de rectificación |
| POST | `/api/rectificacion-riesgo/registros/{id}/solicitudes` | Envía la solicitud al docente aprobador |
| POST | `/api/rectificacion-riesgo/registros/{id}/rectificar-sin-confirmacion` | Anula el registro sin confirmación (docente inactivo y plazo cumplido) |
| GET | `/api/rectificacion-riesgo/solicitudes/{id}/evidencia` | Descarga la imagen con que el docente rechazó una solicitud |

> La ruta `POST …/registros/{id}/rectificar`, que anulaba el registro de inmediato, **ya no existe** (responde 404): se reemplazó por las dos rutas de arriba.

Los `{id}` y `{id_estudiante}` son de 1 a 9 dígitos; otro formato responde **404**. No existe PUT, PATCH ni DELETE en ninguna ruta de la Central de Riesgo, ni para editar, ni para borrar, ni para reactivar (`RiskCenterReadOnlyTest` lo vigila).

### Quién puede usarlas

Solo un **Administrador** con la cuenta **activa** (capacidad `risk_rectify`, `riesgo.rectificar` en el cliente). Un Docente, un Invitado, un Auxiliar, una cuenta sin rol o deshabilitada recibe **403** («No tiene permiso para rectificar registros de la Central de Riesgo.»); sin token, **401**.

### GET /api/rectificacion-riesgo/estudiantes

| Parámetro | Regla |
|---|---|
| `q` | Obligatorio, hasta 60 caracteres. Un código SIS o un carnet se busca **exacto**. Un nombre o apellido se busca **sin distinguir mayúsculas ni tildes**, con palabras parciales, y exige **al menos 3 letras** («Para buscar por nombre ingrese al menos 3 caracteres.») |

Devuelve hasta 10 coincidencias (`meta.limite`); `meta.hay_mas` indica que hay más y conviene afinar la búsqueda. Sin coincidencias responde **200** con `data: []`. Con una sola coincidencia, la interfaz abre directamente los registros del estudiante.

```json
{
  "data": [
    { "id_estudiante": 9702, "nombre_completo": "Carla Condori Vega", "cod_sis": "202170103", "ci": "7400103" }
  ],
  "meta": { "limite": 10, "hay_mas": false }
}
```

### GET /api/rectificacion-riesgo/estudiantes/{id_estudiante}/registros

Un estudiante que no existe responde **404**. Un estudiante sin registros activos responde **200** con `registros: []`.

- `registros`: los registros **activos**, del más reciente al más antiguo. Cada uno trae el bloque `rectificacion` (ver abajo).
- `rectificados`: los ya rectificados, del más reciente al más antiguo, con quién y por qué. Es solo lectura.

```json
{
  "data": {
    "estudiante": { "id_estudiante": 9702, "nombre_completo": "Carla Condori Vega", "cod_sis": "202170103", "ci": "7400103" },
    "registros": [
      {
        "id_cr_estudiante": 9964,
        "fecha_registro": "2026-10-01T15:30:00+00:00",
        "examen": { "id_examen": 9807, "nombre": "1er Parcial", "materia": "Cálculo I", "fecha": "2026-09-10" },
        "tipo_falta": { "id_falta": 1, "nombre": "Copia en examen" },
        "aprobador": { "nombre_completo": "Marcelo Quiroga Andrade", "fecha": "2026-10-01T15:30:00+00:00" },
        "rectificacion": {
          "solicitud_pendiente": null,
          "rechazos": 2,
          "vencidas": 1,
          "ultimo_rechazo": {
            "id_solicitud_rect": 9511,
            "motivo": "El reporte tiene respaldo en la grabación del aula.",
            "fecha": "2026-11-12T14:00:00+00:00",
            "evidencia": { "nombre_archivo": "u3xk.png", "tamano_bytes": 70, "disponible": true }
          },
          "docente": {
            "nombre_completo": "Marcelo Quiroga Andrade",
            "activo": true,
            "inactivo_desde": null,
            "fuente": null,
            "puede_rectificar_desde": null,
            "puede_rectificar_sin_confirmacion": false
          },
          "dias_vencimiento": 7,
          "dias_docente_inactivo": 180
        }
      }
    ],
    "rectificados": [
      {
        "id_cr_estudiante": 9961,
        "fecha_registro": "2026-09-11T19:00:00+00:00",
        "examen": { "id_examen": 9807, "nombre": "1er Parcial", "materia": "Cálculo I", "fecha": "2026-09-10" },
        "tipo_falta": { "id_falta": 3, "nombre": "Material no permitido" },
        "aprobador": { "nombre_completo": "Marcelo Quiroga Andrade", "fecha": "2026-09-11T19:00:00+00:00" },
        "rectificacion": {
          "fecha": "2026-10-05T14:00:00+00:00",
          "administrador": { "nombre_completo": "Administrador del Sistema" },
          "motivo": "Se confundió a la estudiante: el reporte correspondía a otra persona de la fila."
        }
      }
    ]
  }
}
```

**El bloque `rectificacion` de un registro activo:**

| Campo | Significado |
|---|---|
| `solicitud_pendiente` | La solicitud pendiente y vigente (`id_solicitud_rect`, `motivo`, `fecha_solicitud`, `vence_en`, `docente.nombre_completo`) o `null`. Con una pendiente la interfaz muestra «Esperando confirmación del docente» y no ofrece enviar otra |
| `rechazos` | Cuántas solicitudes rechazó el docente («Rectificación rechazada N veces») |
| `vencidas` | Cuántas vencieron sin respuesta (cuenta aparte, incluida una pendiente pasada de fecha) |
| `ultimo_rechazo` | El último rechazo: motivo, fecha y evidencia (`null` si no adjuntó imagen), descargable en `GET /api/rectificacion-riesgo/solicitudes/{id}/evidencia`; `null` si nunca se rechazó |
| `docente` | Estado del docente aprobador: `activo`, `inactivo_desde`, `fuente` (`ROL_CERRADO` o `PRIMERA_DETECCION`), `puede_rectificar_desde` y `puede_rectificar_sin_confirmacion` (si ya se puede usar la ruta directa) |
| `dias_vencimiento`, `dias_docente_inactivo` | Los plazos configurados, para que la interfaz los muestre sin fijarlos en el cliente |

### GET /api/rectificacion-riesgo/registros/{id}

Ficha completa de un registro **activo**, para que el administrador revise todo antes de decidir. Un registro que no existe **o que ya fue rectificado** responde **404**. Además de los datos del hecho trae el mismo bloque `rectificacion`.

```json
{
  "data": {
    "id_cr_estudiante": 9964,
    "estudiante": { "id_estudiante": 9702, "nombre_completo": "Carla Condori Vega", "cod_sis": "202170103", "ci": "7400103" },
    "examen": { "id_examen": 9807, "nombre": "1er Parcial", "materia": "Cálculo I", "carrera": "Ingeniería de Sistemas", "fecha": "2026-09-10" },
    "hecho": { "fecha_hora": "2026-09-30T12:50:00+00:00" },
    "ambiente": { "nro_aula": "691A" },
    "tipo_falta": { "id_falta": 1, "nombre": "Copia en examen" },
    "descripcion": "Miraba repetidamente la hoja de su compañero.",
    "evidencia": { "nombre_archivo": "9921.png", "tamano_bytes": 70, "disponible": true },
    "reportante": { "nombre_completo": "Daniela Ferrufino Soliz", "fecha": "2026-09-30T12:50:00+00:00" },
    "aprobador": { "nombre_completo": "Marcelo Quiroga Andrade", "fecha": "2026-10-01T15:30:00+00:00" },
    "fecha_registro": "2026-10-01T15:30:00+00:00",
    "rectificacion": { "solicitud_pendiente": null, "rechazos": 0, "vencidas": 0, "ultimo_rechazo": null, "docente": { "activo": true }, "dias_vencimiento": 7, "dias_docente_inactivo": 180 }
  }
}
```

- `hecho.fecha_hora` es la del intento de ingreso si el reporte nace de uno y, si no, la del reporte.
- `ambiente` es `null` si no se conoce el aula.
- `evidencia` es `null` si el reporte no tiene archivo; el archivo se baja de `GET /api/central-riesgo/{id}/evidencia` (HU-046), que mientras el registro esté activo sirve al Administrador.

### POST /api/rectificacion-riesgo/registros/{id}/solicitudes

| Campo | Regla |
|---|---|
| `motivo` | Obligatorio, de 10 a 500 caracteres, se recorta («El motivo es obligatorio» si falta). Cualquier otro campo se ignora |

**201** con la solicitud enviada: `id_solicitud_rect`, `id_cr_estudiante`, `estado` (`PENDIENTE`), `motivo`, `docente.nombre_completo`, `fecha_solicitud` y `vence_en`. El registro **no** se toca.

| Código | Cuándo |
|---|---|
| 401 / 403 | Sin token / sin permiso |
| 404 | El registro no existe |
| 409 | El registro ya fue rectificado («Este registro ya fue rectificado y ya no está en la Central de Riesgo.») |
| 409 | Ya hay una solicitud pendiente y vigente («Este registro ya tiene una solicitud de rectificación pendiente de respuesta del docente.») |
| 409 | El docente aprobador ya no está activo (con la fecha desde la que podrá rectificarse sin su confirmación, o la indicación de usar la ruta directa si el plazo ya se cumplió) |
| 422 | El motivo no cumple su regla; `errors` indica cuál |

### POST /api/rectificacion-riesgo/registros/{id}/rectificar-sin-confirmacion

Mismo cuerpo que la solicitud (`motivo`). **200** con el registro ya rectificado (la misma forma que un elemento de `rectificados`).

| Código | Cuándo |
|---|---|
| 401 / 403 / 404 / 422 | Igual que arriba |
| 409 | El registro ya fue rectificado |
| 409 | El docente aprobador sigue activo («…sigue activo: la rectificación necesita su confirmación. Envíele una solicitud.») |
| 409 | El docente está inactivo pero aún no pasó el plazo («…Podrá rectificarlo sin su confirmación a partir del {fecha}.») |

## Rutas del docente

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/solicitudes-rectificacion` | Bandeja: pendientes primero (la que vence antes, primero), luego respondidas y vencidas |
| GET | `/api/solicitudes-rectificacion/conteo` | Cuántas solicitudes pendientes y vigentes tiene (para el badge del menú) |
| POST | `/api/solicitudes-rectificacion/{id}/aceptar` | Acepta: el registro sale de la Central |
| POST | `/api/solicitudes-rectificacion/{id}/rechazar` | Rechaza con motivo y, si quiere, una imagen |
| GET | `/api/solicitudes-rectificacion/{id}/evidencia-reporte` | Descarga la evidencia del reporte original (si existe) |
| GET | `/api/solicitudes-rectificacion/{id}/evidencia-rechazo` | Descarga la imagen del rechazo (si la hay) |

### Quién puede usarlas

Las cuentas **activas** cuyo rol ejerce la función `risk_rectify_confirm` (`riesgo.confirmar_rectificacion` en el cliente): **Docente** e **Invitado**. El Invitado también aprueba reportes y es el único que puede responder lo que él aprobó, aunque no consulte la Central de Riesgo. Un Administrador o un Auxiliar recibe **403** («No tiene permiso para responder solicitudes de rectificación.»). Además, **solo el docente que aprobó el reporte** puede ver y responder cada solicitud: otro docente recibe **403** («Solo el docente que aprobó este registro puede responder la solicitud de rectificación.»).

### GET /api/solicitudes-rectificacion

`data` es la lista de tarjetas y `meta.total_pendientes` cuenta las pendientes **vigentes** (las vencidas no cuentan). Se devuelven todas las pendientes y las 50 respondidas o vencidas más recientes.

```json
{
  "data": [
    {
      "id_solicitud_rect": 9511,
      "id_cr_estudiante": 9964,
      "estado": "PENDIENTE",
      "fecha_solicitud": "2026-11-20T15:00:00+00:00",
      "vence_en": "2026-11-27T15:00:00+00:00",
      "fecha_respuesta": null,
      "motivo_solicitud": "El reporte correspondía a otra persona de la fila.",
      "administrador": { "nombre_completo": "Administrador del Sistema" },
      "estudiante": { "id_estudiante": 9702, "nombre_completo": "Carla Condori Vega", "cod_sis": "202170103" },
      "examen": { "id_examen": 9807, "nombre": "1er Parcial", "materia": "Cálculo I", "fecha": "2026-09-10" },
      "hecho": { "fecha_hora": "2026-09-30T12:50:00+00:00" },
      "tipo_falta": { "id_falta": 1, "nombre": "Copia en examen" },
      "descripcion": "Miraba repetidamente la hoja de su compañero.",
      "fecha_registro": "2026-10-01T15:30:00+00:00",
      "evidencia": { "nombre_archivo": "9921.png", "tamano_bytes": 70, "disponible": true },
      "rechazos_previos": 2,
      "registro_rectificado": false,
      "respuesta": null
    }
  ],
  "meta": { "total_pendientes": 1 }
}
```

- `estado` es el efectivo: una pendiente pasada de fecha llega como `VENCIDA`.
- `rechazos_previos` cuenta las solicitudes rechazadas del mismo registro enviadas antes de esta; la interfaz muestra «Este registro ya tuvo N solicitudes de rectificación rechazadas».
- `respuesta` solo existe en una solicitud `RECHAZADA`: `{ "motivo": "…", "evidencia": { … } | null }`.

### GET /api/solicitudes-rectificacion/conteo

```json
{ "data": { "total_pendientes": 1 } }
```

### POST /api/solicitudes-rectificacion/{id}/aceptar

Sin cuerpo. **200** con la tarjeta actualizada (`estado: ACEPTADA`, `registro_rectificado: true`).

### POST /api/solicitudes-rectificacion/{id}/rechazar

Cuerpo `multipart/form-data` (o JSON si no hay imagen):

| Campo | Regla |
|---|---|
| `motivo` | Obligatorio, de 10 a 500 caracteres, se recorta («El motivo es obligatorio») |
| `evidencia` | Opcional. Imagen jpg, png o webp de hasta 5 MB («Formato o tamaño no permitido. Use una imagen jpg, png o webp de hasta 5 MB.»). Se guarda en el disco privado bajo `evidencias/rectificaciones/` |

**200** con la tarjeta actualizada (`estado: RECHAZADA`, con `respuesta`).

| Código | Cuándo (aceptar y rechazar) |
|---|---|
| 401 / 403 | Sin token / sin la capacidad / solicitud de otro docente |
| 404 | La solicitud no existe |
| 409 | La solicitud venció («La solicitud de rectificación venció y ya no puede responderse. El registro sigue en la Central.») |
| 409 | La solicitud ya fue respondida |
| 409 | El registro ya fue rectificado (la solicitud, si seguía pendiente, se cierra como `VENCIDA`) |
| 422 | Motivo o imagen inválidos |

### Qué ocurre al responder

Todo en **una sola transacción**. Primero se marca `VENCIDA` lo que ya venció (en su propia transacción, para que la marca se conserve aunque la respuesta sea 409) y luego se bloquea con `FOR UPDATE` **la solicitud y, después, la fila del registro** (siempre en ese orden). Se verifica que exista, que sea de ese docente, que esté `PENDIENTE` y vigente y que el registro siga activo.

- **Aceptar**: marca el registro como rectificado con el administrador solicitante y su motivo, marca la solicitud `ACEPTADA` y escribe en la bitácora `ACEPTAR_RECTIFICACION` (tabla `solicitud_rectificacion`) **y** `RECTIFICAR_REGISTRO` (tabla `central_riesgo`, firmada por el docente). `RECTIFICAR_REGISTRO` se sigue escribiendo porque es la señal de que el registro salió de la Central. Después de confirmar se actualizan los **antecedentes del estudiante en los controles de ingreso abiertos** (snapshot en Redis); si esa actualización falla, la rectificación **no se revierte** y se registra una advertencia.
- **Rechazar**: guarda la imagen (solo con todas las validaciones superadas; si la transacción falla, el archivo se borra), marca la solicitud `RECHAZADA` y escribe `RECHAZAR_RECTIFICACION`. El registro y los antecedentes no se tocan.
- **Rectificar sin confirmación**: bloquea el registro, verifica el estado del docente y marca el registro con el administrador y su motivo. Escribe **solo** `RECTIFICAR_SIN_CONFIRMACION`, con el registro, el docente inactivo (`id_usuario_docente_inactivo`), el motivo, la fecha desde la que se considera inactivo (`inactivo_desde`) y su origen (`fuente_inactividad`: `ROL_CERRADO` o `PRIMERA_DETECCION`). Si el registro tenía solicitudes `PENDIENTE`, las cierra como `VENCIDA` en la misma transacción (sin respuesta, sin rechazo y sin entrada de bitácora propia) y deja sus ids en `solicitudes_cerradas` del `nuevo_valor`; así ninguna queda huérfana ni cuenta como pendiente del docente.

El envío de una solicitud (`SOLICITAR_RECTIFICACION`) bloquea el registro, marca las vencidas de ese registro (liberan el índice único), comprueba al docente y que no haya otra pendiente, y la inserta. Si una pendiente pasada de fecha todavía no fue marcada por la tarea, no bloquea una solicitud nueva.

### Bitácora

| Acción | Cuándo | Tabla afectada |
|---|---|---|
| `SOLICITAR_RECTIFICACION` | El administrador envía la solicitud | `solicitud_rectificacion` |
| `ACEPTAR_RECTIFICACION` | El docente acepta | `solicitud_rectificacion` |
| `RECHAZAR_RECTIFICACION` | El docente rechaza | `solicitud_rectificacion` |
| `RECTIFICAR_REGISTRO` | El registro sale de la Central porque el docente aceptó | `central_riesgo` |
| `RECTIFICAR_SIN_CONFIRMACION` | El administrador retira el registro por docente inactivo | `central_riesgo` |

El vencimiento **no** se registra. Los registros rectificados antes de este cambio conservan sus datos; no hay migración de datos ni se crean solicitudes retroactivas.

## Notas de modelo de datos

- Tres columnas en `central_riesgo` (`docs/database/creation-script.sql`, versión 14): `rectificado_en`, `id_usuario_rectificador` y `motivo_rectificacion`; todas nulas (activo) o todas con valor (rectificado). Esta historia no agrega columnas a esa tabla.
- `solicitud_rectificacion` (versión 20): una fila por solicitud, con `estado` (`PENDIENTE`, `ACEPTADA`, `RECHAZADA`, `VENCIDA`) y CHECK por estado. Índice único parcial: una sola `PENDIENTE` por registro. `id_usuario_docente` guarda al aprobador al momento de enviar.
- `docente_inactivo_deteccion` (versión 20): la primera detección de un docente inactivo (ver «De dónde sale la fecha de inactividad»).
- Configuración (`config/sciem.php`): `rectificacion.dias_vencimiento` (`SCIEM_RECTIFICACION_DIAS_VENCIMIENTO`, 7) y `rectificacion.dias_docente_inactivo` (`SCIEM_RECTIFICACION_DIAS_DOCENTE_INACTIVO`, 180).
- Los caminos de escritura son `RectificationRequestService` (enviar, aceptar, rechazar) y `RiskCenterRectificationService` (anular el registro, también sin confirmación); no hay trigger, lo garantizan los servicios y los tests.
- Los datos de prueba están en `Sprint5QaSeeder` (`database/seeders/TestData`, ids 9000–9999, idempotente, solo en la base local, datos ficticios): incluye solicitudes pendiente, aceptada, rechazada con imagen y vencida de un docente, un registro con dos rechazos previos y registros cuyo docente aprobador está inactivo hace menos y más de los días configurados.
