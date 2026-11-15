# API de rectificación de la Central de Riesgo (HU-062)

Permite a un **Administrador** corregir el **tipo de falta** y la **descripción** de un registro confirmado de la Central de Riesgo de estudiantes. El registro original **nunca se sobrescribe**: cada rectificación crea una **versión nueva** y la anterior queda en el historial.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `meta` en la búsqueda); los errores llevan `message` (y `errors` por campo en los 422). Middlewares: `auth:sanctum`, `account.active`, `password.changed`.

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/rectificacion-riesgo/estudiantes?q=` | Busca estudiantes por código SIS, carnet o nombre |
| GET | `/api/rectificacion-riesgo/estudiantes/{id_estudiante}/registros` | Registros confirmados de un estudiante, con su estado |
| GET | `/api/rectificacion-riesgo/tipos-falta` | Catálogo de tipos de falta para el formulario |
| GET | `/api/rectificacion-riesgo/registros/{id}` | Detalle de un registro con su historial completo |
| POST | `/api/rectificacion-riesgo/registros/{id}/rectificar` | Crea una versión nueva del registro |

Los `{id}` y `{id_estudiante}` son de 1 a 9 dígitos; otro formato responde **404**. No existe PUT, PATCH ni DELETE en ninguna ruta de la Central de Riesgo, ni sobre registros ni sobre versiones (`RiskCenterReadOnlyTest` lo vigila).

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

Un estudiante que no existe responde **404**. Un estudiante sin registros responde **200** con `registros: []` (la interfaz muestra «El estudiante no tiene registros en la Central de Riesgo»). Los registros van del más reciente al más antiguo.

Cada registro trae su **estado**: `Original` si nunca se rectificó o `Rectificado`, con el número de rectificaciones y la fecha de la última. El tipo de falta es siempre el **vigente**.

```json
{
  "data": {
    "estudiante": { "id_estudiante": 9702, "nombre_completo": "Carla Condori Vega", "cod_sis": "202170103", "ci": "7400103" },
    "registros": [
      {
        "id_cr_estudiante": 9961,
        "fecha_registro": "2026-09-11T19:00:00+00:00",
        "examen": { "id_examen": 9805, "nombre": "1er Parcial", "materia": "Cálculo I", "fecha": "2026-09-10" },
        "tipo_falta": { "id_falta": 1, "nombre": "Copia en examen" },
        "aprobador": { "nombre_completo": "Marcelo Quiroga Andrade", "fecha": "2026-09-11T19:00:00+00:00" },
        "estado": "Rectificado",
        "numero_rectificaciones": 1,
        "ultima_rectificacion": "2026-10-05T14:00:00+00:00",
        "version_vigente": 2
      }
    ]
  }
}
```

Estos campos de estado viajan **solo** en los endpoints de esta historia; la consulta de HU-046 (`/api/central-riesgo`) conserva sus claves, aunque su `tipo_falta` y `descripcion` reflejan la versión vigente.

## GET /api/rectificacion-riesgo/registros/{id}

Un registro que no existe responde **404**. Además de los campos del listado, trae lo que no se puede cambiar (estudiante, examen, ambiente, reportante, evidencia), la `descripcion` vigente y el `historial` completo, **la versión más nueva primero**. Un registro nunca rectificado muestra su valor original como versión 1 vigente.

```json
{
  "data": {
    "id_cr_estudiante": 9961,
    "estado": "Rectificado",
    "numero_rectificaciones": 1,
    "ultima_rectificacion": "2026-10-05T14:00:00+00:00",
    "version_vigente": 2,
    "tipo_falta": { "id_falta": 1, "nombre": "Copia en examen" },
    "descripcion": "El material contenía las respuestas.",
    "historial": [
      {
        "numero_version": 2,
        "estado": "Vigente",
        "tipo_falta": { "id_falta": 1, "nombre": "Copia en examen" },
        "descripcion": "El material contenía las respuestas.",
        "motivo": "La evidencia confirma que se trataba de copia.",
        "autor": { "nombre_completo": "Administrador del Sistema" },
        "fecha": "2026-10-05T14:00:00+00:00"
      },
      {
        "numero_version": 1,
        "estado": "Anterior",
        "tipo_falta": { "id_falta": 3, "nombre": "Material no permitido" },
        "descripcion": "Tenía material impreso.",
        "motivo": null,
        "autor": { "nombre_completo": "Marcelo Quiroga Andrade" },
        "fecha": "2026-09-11T19:00:00+00:00"
      }
    ]
  }
}
```

(El ejemplo omite `estudiante`, `examen`, `ambiente`, `reportante`, `evidencia`, `aprobador` y `fecha_registro` para abreviar.) La evidencia viaja solo con su nombre y si está disponible; esta vista no la enlaza.

## POST /api/rectificacion-riesgo/registros/{id}/rectificar

| Campo | Regla |
|---|---|
| `id_tipo_falta` | Obligatorio. `id_falta` de `GET /api/rectificacion-riesgo/tipos-falta` |
| `descripcion` | Opcional, hasta 2000 caracteres. Se recorta; vacía se guarda como `null` |
| `motivo` | Obligatorio, de 10 a 500 caracteres («El motivo es obligatorio» si falta) |
| `version_base` | Obligatorio, entero ≥ 1: la `version_vigente` que el administrador estaba viendo |

**201** con el mismo detalle que `GET /registros/{id}`, ya con la versión nueva vigente.

| Código | Cuándo |
|---|---|
| 401 / 403 | Sin token / sin permiso |
| 404 | El registro no existe |
| 409 | `version_base` ya no es la vigente («El registro fue rectificado mientras usted lo editaba. Recargue para ver la versión vigente.»), o los valores son iguales a los vigentes («Los valores indicados son iguales a los de la versión vigente.») |
| 422 | Un campo no cumple su regla; `errors` indica cuál |

### Qué ocurre al rectificar

Todo en **una sola transacción**, serializada con un bloqueo sobre la fila del registro:

1. Se compara `version_base` con la vigente (409 si difiere) y se verifica que haya algún cambio.
2. Si es la primera rectificación, se crea la versión 1 con los valores originales del reporte (autor: quien aprobó el reporte; fecha: la del registro).
3. La versión vigente pasa a «Anterior» y se crea la versión `n + 1` como vigente, con el motivo y el administrador autor. Un índice único parcial impide dos vigentes a la vez.
4. Se escribe una fila de bitácora `RECTIFICAR_REGISTRO` sobre la tabla `central_riesgo`, con los valores antes y después (y el motivo).

Después de confirmar la transacción se actualizan los **antecedentes del estudiante en los controles de ingreso abiertos** (snapshot en Redis). Si esa actualización falla, la rectificación **no se revierte**: se registra una advertencia en el log y los siguientes snapshots salen con el dato correcto.

## GET /api/rectificacion-riesgo/tipos-falta

Catálogo propio de la rectificación (no depende de la consulta de HU-046): `data` es una lista de `{ id_falta, nombre, descripcion }`.

## Notas de modelo de datos

- `central_riesgo_version` (`docs/database/creation-script.sql`, versión 14): una fila por versión, con `es_vigente` y un índice único parcial por registro.
- La inmutabilidad la garantiza el servicio y la prueban los tests (no hay trigger): el único camino de escritura es `RiskCenterRectificationService::rectify`.
- Los datos de prueba están en `Sprint5QaSeeder` (`database/seeders/TestData`, ids 9000–9999, idempotente, solo en la base local).
