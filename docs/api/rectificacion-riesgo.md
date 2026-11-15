# API de rectificación de la Central de Riesgo (HU-062)

Permite a un **Administrador** **rectificar** un registro confirmado de la Central de Riesgo de estudiantes. Rectificar es **anular** el registro: se usa cuando fue aprobado por error (estudiante equivocado, reporte sin sustento, etc.) y el administrador lo **retira de la Central**. No se edita el tipo de falta ni la descripción.

La anulación es **lógica e irreversible**: la fila de `central_riesgo` no se borra, se marca con la fecha, el administrador y el motivo. No existe forma de reactivarla. Un registro rectificado **ya no cuenta como antecedente** en ninguna parte del sistema (ver «Dónde deja de contar» en [central-riesgo.md](central-riesgo.md)). El reporte original, su evidencia y su revisión no se modifican.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `meta` en la búsqueda); los errores llevan `message` (y `errors` por campo en los 422). Middlewares: `auth:sanctum`, `account.active`, `password.changed`.

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/rectificacion-riesgo/estudiantes?q=` | Busca estudiantes por código SIS, carnet o nombre |
| GET | `/api/rectificacion-riesgo/estudiantes/{id_estudiante}/registros` | Registros activos y registros ya rectificados de un estudiante |
| GET | `/api/rectificacion-riesgo/registros/{id}` | Ficha completa de un registro **activo** |
| POST | `/api/rectificacion-riesgo/registros/{id}/rectificar` | Anula el registro (lo retira de la Central) |

Los `{id}` y `{id_estudiante}` son de 1 a 9 dígitos; otro formato responde **404**. No existe PUT, PATCH ni DELETE en ninguna ruta de la Central de Riesgo, ni para editar, ni para borrar, ni para reactivar (`RiskCenterReadOnlyTest` lo vigila).

## Quién puede usarla

Solo un **Administrador** con la cuenta **activa** (capacidad `risk_rectify`). Un Docente, un Auxiliar, una cuenta sin rol o deshabilitada recibe **403** («No tiene permiso para rectificar registros de la Central de Riesgo.»); sin token, **401**. Un Docente sigue consultando la Central con la consulta de HU-046, sin controles de rectificación.

## GET /api/rectificacion-riesgo/estudiantes

| Parámetro | Regla |
|---|---|
| `q` | Obligatorio, hasta 60 caracteres. Un código SIS o un carnet se busca **exacto**. Un nombre o apellido se busca **sin distinguir mayúsculas ni tildes**, con palabras parciales, y exige **al menos 3 letras** («Para buscar por nombre ingrese al menos 3 caracteres.») |

Devuelve hasta 10 coincidencias (`meta.limite`); `meta.hay_mas` indica que hay más y conviene afinar la búsqueda. Sin coincidencias responde **200** con `data: []`: la interfaz muestra «No se encontró ningún estudiante con ese dato». Con una sola coincidencia, la interfaz abre directamente los registros del estudiante.

```json
{
  "data": [
    { "id_estudiante": 9702, "nombre_completo": "Carla Condori Vega", "cod_sis": "202170103", "ci": "7400103" }
  ],
  "meta": { "limite": 10, "hay_mas": false }
}
```

## GET /api/rectificacion-riesgo/estudiantes/{id_estudiante}/registros

Un estudiante que no existe responde **404**. Un estudiante sin registros activos responde **200** con `registros: []` (la interfaz muestra «El estudiante no tiene registros activos en la Central de Riesgo»).

- `registros`: los registros **activos**, del más reciente al más antiguo. Son los que se pueden rectificar.
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
        "aprobador": { "nombre_completo": "Marcelo Quiroga Andrade", "fecha": "2026-10-01T15:30:00+00:00" }
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

## GET /api/rectificacion-riesgo/registros/{id}

Ficha completa de un registro **activo**, para que el administrador revise todo antes de decidir. Un registro que no existe **o que ya fue rectificado** responde **404**.

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
    "fecha_registro": "2026-10-01T15:30:00+00:00"
  }
}
```

- `hecho.fecha_hora` es la del intento de ingreso si el reporte nace de uno y, si no, la del reporte.
- `ambiente` es `null` si no se conoce el aula.
- `evidencia` es `null` si el reporte no tiene archivo; el archivo se baja de `GET /api/central-riesgo/{id}/evidencia` (HU-046), que mientras el registro esté activo sirve al Administrador.

## POST /api/rectificacion-riesgo/registros/{id}/rectificar

| Campo | Regla |
|---|---|
| `motivo` | Obligatorio, de 10 a 500 caracteres, se recorta («El motivo es obligatorio» si falta). Cualquier otro campo se ignora |

**200** con el registro ya rectificado (la misma forma que un elemento de `rectificados`).

| Código | Cuándo |
|---|---|
| 401 / 403 | Sin token / sin permiso |
| 404 | El registro no existe |
| 409 | El registro ya fue rectificado («Este registro ya fue rectificado y ya no está en la Central de Riesgo.»), por quien sea |
| 422 | El motivo no cumple su regla; `errors` indica cuál |

### Qué ocurre al rectificar

Todo en **una sola transacción**, serializada con un bloqueo (`FOR UPDATE`) sobre la fila del registro:

1. Se bloquea el registro y se verifica que siga activo (409 si no).
2. Se marcan `rectificado_en`, `id_usuario_rectificador` y `motivo_rectificacion` con un `UPDATE` condicionado a `rectificado_en IS NULL` que debe afectar exactamente una fila. Una columna `CHECK` obliga a que las tres vayan juntas.
3. Se escribe una fila de bitácora `RECTIFICAR_REGISTRO` sobre `central_riesgo`: administrador, registro, estudiante, examen, motivo y fecha y hora. `antiguo_valor` lleva `estado: ACTIVO` y `nuevo_valor`, `estado: RECTIFICADO` más el motivo. Si la bitácora falla, todo se revierte.

Después de confirmar se actualizan los **antecedentes del estudiante en los controles de ingreso abiertos** (snapshot en Redis), de modo que el aviso desaparece. Si esa actualización falla, la rectificación **no se revierte**: se registra una advertencia en el log y los siguientes snapshots salen con el dato correcto.

## Notas de modelo de datos

- Tres columnas nuevas en `central_riesgo` (`docs/database/creation-script.sql`, versión 14): `rectificado_en`, `id_usuario_rectificador` y `motivo_rectificacion`; todas nulas (activo) o todas con valor (rectificado). No hay tabla de versiones.
- El único camino de escritura es `RiskCenterRectificationService::rectify`; no hay trigger, lo garantizan el servicio y los tests.
- Los datos de prueba están en `Sprint5QaSeeder` (`database/seeders/TestData`, ids 9000–9999, idempotente, solo en la base local, datos ficticios): Carla Condori tiene dos registros activos y uno rectificado, María Pérez uno activo, y Luis Perez y Daniel Rojas ninguno.
