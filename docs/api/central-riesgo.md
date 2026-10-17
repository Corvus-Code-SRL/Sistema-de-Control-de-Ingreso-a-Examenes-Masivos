# API de la Central de Riesgo de estudiantes (HU-046)

Consulta **institucional y de solo lectura** de los antecedentes confirmados: una fila por reporte de estudiante **aprobado** (`central_riesgo`). El registro no se filtra por docente ni por examen: un Docente ve todos los registros de la institución.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `meta`/`links` en el listado); los errores llevan `message` (y `errors` por campo en los 422).

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/central-riesgo` | Listado paginado, con búsqueda y filtro por tipo de falta |
| GET | `/api/central-riesgo/{id}` | Detalle de un registro |
| GET | `/api/central-riesgo/{id}/evidencia` | Archivo de evidencia del registro (autenticado) |

No existe ninguna ruta de escritura (POST, PUT, PATCH, DELETE) bajo `/api/central-riesgo`; `RiskCenterReadOnlyTest` lo vigila. Rectificar un registro es otra historia (HU-062).

## Quién puede consultar

Solo un **Docente** o un **Administrador** con la cuenta **activa**. Un Auxiliar, una cuenta sin rol o deshabilitada recibe **403**; sin token, **401**. Las lecturas **no generan entradas de bitácora** (la bitácora registra escrituras).

Middlewares: `auth:sanctum`, `account.active`, `password.changed`.

## GET /api/central-riesgo

| Parámetro | Regla |
|---|---|
| `q` | Opcional, hasta 50 caracteres. Busca por nombre completo o código SIS del estudiante, **sin distinguir mayúsculas ni tildes** («jose» encuentra «José»). Cada palabra debe coincidir (se consideran hasta 5); `%` y `_` se buscan como texto |
| `tipo` | Opcional. `id_falta` de `GET /api/tipos-falta`; entero entre 1 y 2147483647 |
| `page` | Opcional, entero entre 1 y 100000. Una página sin filas responde 200 con `data: []` |
| `per_page` | Opcional, entero entre 1 y 50 (10 por defecto) |

Un valor fuera de rango responde **422** con `errors` por campo. Orden: `fecha_registro` descendente y, a igual fecha, el id más reciente primero.

**200**

```json
{
  "data": [
    {
      "id_cr_estudiante": 9945,
      "estudiante": { "id_estudiante": 9525, "nombre_completo": "José Pérez Mamani", "cod_sis": "202150025" },
      "tipo_falta": { "id_falta": 3, "nombre": "Material no permitido" },
      "examen": { "id_examen": 9805, "nombre": "1er Parcial", "materia": "Cálculo I" },
      "fecha_registro": "2026-09-22T12:00:00+00:00"
    }
  ],
  "links": { "first": "…", "last": "…", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 10, "to": 1, "total": 1 }
}
```

Las fechas son ISO 8601 (UTC). La interfaz las muestra en `America/La_Paz`. `examen.materia` es `null` si el examen no tiene materia.

## GET /api/central-riesgo/{id}

`{id}` son hasta 10 dígitos; un id que no existe, no es numérico o excede 2147483647 responde **404** (nunca 500).

**200**

```json
{
  "data": {
    "id_cr_estudiante": 9941,
    "estudiante": { "id_estudiante": 9525, "nombre_completo": "José Pérez Mamani", "cod_sis": "202150025" },
    "tipo_falta": { "id_falta": 1, "nombre": "Copia en examen" },
    "examen": { "id_examen": 9807, "nombre": "1er Parcial", "materia": "Cálculo I", "carrera": "Ingeniería de Sistemas", "fecha": "2026-09-10" },
    "ambiente": { "nro_aula": "691A", "hora_intento": "2026-09-10T12:12:00+00:00" },
    "descripcion": "Copiaba respuestas de su compañero.",
    "reportante": { "nombre_completo": "Daniela Ferrufino Soliz", "fecha": "2026-09-10T12:40:00+00:00" },
    "aprobador": { "nombre_completo": "Marcelo Quiroga Andrade", "fecha": "2026-09-10T20:00:00+00:00" },
    "observacion": "Confirmado con el controlador.",
    "fecha_registro": "2026-09-10T20:00:00+00:00",
    "evidencia": { "nombre_archivo": "9921.png", "tamano_bytes": 70, "disponible": true }
  }
}
```

- `examen.carrera` es la carrera **del examen** (`examen.id_carrera → carrera.nombre`), no la del estudiante: un estudiante puede cursar varias carreras a la vez y su código SIS no depende de una carrera. Es `null` si el examen no tiene carrera, y `estudiante` no lleva carrera.
- `ambiente` es `null` salvo que el reporte nazca de un intento de ingreso (`id_intento`); entonces trae el aula y la hora del intento.
- `evidencia` es `null` si el reporte no tiene evidencia. **No trae la URL pública**: el archivo se pide a `…/evidencia`. `disponible` es `false` si la ruta guardada no apunta a un archivo existente (y `tamano_bytes` es `null`).
- `aprobador` y `observacion` pueden ser `null`.

## GET /api/central-riesgo/{id}/evidencia

Entrega el archivo con la sesión; la interfaz lo pide como `Blob` con el token y lo abre desde una dirección local temporal. **No hay enlace público.**

- Lee la ruta relativa de `reporte_estudiante.evidencia_public_id` en los discos de `config('sciem.evidencia_discos')` (variable `SCIEM_EVIDENCIA_DISCOS`, por defecto `local,public`), en ese orden: primero el privado (`storage/app`), luego el público. **No depende de `storage:link` ni de `APP_URL`.**
- La ruta debe colgar de `evidencias/`; cualquier ruta que salga de ahí (`..`, ruta absoluta, barra invertida, bytes de control) se trata como si no hubiera archivo.
- Cabeceras: `Content-Type` real del archivo, `Content-Disposition: inline; filename=<nombre>` para imagen o PDF y `attachment` para cualquier otro tipo, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'; sandbox` y `Cache-Control: private, no-store`.
- **404** si el registro no existe, no tiene evidencia, la ruta no es segura o el archivo falta en los discos.

## Contrato para el control de ingreso (HU-032)

No hay endpoint HTTP de antecedentes. El backend del control de ingreso consulta el Service directamente (un Service puede inyectar Services de otro módulo):

```php
App\Services\RiskCenter\Academic\RiskCenterQueryService::summaryForStudent(int $studentId): array
```

Devuelve, en **una sola consulta**:

```php
[
    'total' => 2,                       // registros del estudiante en la central
    'latest' => [                       // el más reciente; null si total = 0
        'id_cr_estudiante' => 9945,
        'tipo_falta' => ['id_falta' => 3, 'nombre' => 'Material no permitido'],
        'fecha_registro' => Carbon,     // Carbon\Carbon (UTC)
    ],
]
```

Sin registros (o con un estudiante inexistente) devuelve `['total' => 0, 'latest' => null]`. «El más reciente» es el de mayor `fecha_registro` y, a igualdad, el de mayor `id_cr_estudiante`.

Con esto el controlador (Auxiliar o Docente) ve un **aviso que no bloquea el ingreso**:

- `total = 1`: «Este estudiante tiene un registro en la Central de Riesgo», con el tipo de falta y la fecha.
- `total > 1`: «Este estudiante tiene N registros en la Central de Riesgo», con el último registro.

La redacción y la vista del aviso son del control de ingreso; este módulo solo entrega los datos.

## Nota para HU-042 (guardar evidencias nuevas)

- Guardar los archivos nuevos en un **disco privado** (por ejemplo `storage/app/evidencias/…`, el disco `local`), no en `public`.
- `reporte_estudiante.evidencia_public_id` debe seguir siendo la **ruta relativa** del archivo y empezar por `evidencias/` (p. ej. `evidencias/reportes/<id>/foto_carnet.jpg`). El nombre que ve el usuario es el nombre del archivo de esa ruta: si se quiere conservar el nombre original de la subida, debe ser la última parte de la ruta.
- `evidencia_url` pasa a ser **informativa**: la Central de Riesgo ya no la usa ni la expone.
