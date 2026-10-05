# API de carga de nómina (HU-21)

Carga de la nómina de estudiantes de un grupo desde un archivo **CSV o XLSX**, en dos pasos: un *preview* que solo lee el archivo y devuelve un token, y una *confirmación* que es lo único que escribe.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`. Las respuestas correctas llevan `data`; los errores llevan `message` (y `errors` por campo en los 422 de validación).

| Método | Ruta | Cuerpo |
|---|---|---|
| POST | `/api/grupos/{id_grupo}/nomina/preview` | `multipart/form-data` con `archivo` |
| POST | `/api/grupos/{id_grupo}/nomina/confirm` | JSON con `token` |

`{id_grupo}` es un entero entre 1 y 2147483647; cualquier otro valor responde 422 antes de consultar la base.

## Quién puede cargar la nómina

- Solo el **docente que dicta el grupo**. Una cuenta con rol **Auxiliar** nunca puede, aunque el grupo figure a nombre del docente.
- El grupo debe estar **activo**, ser del **período activo** y no tener un examen **en ingreso o en curso** (con el examen programado la nómina cambia libremente: los participantes se derivan de la nómina y nunca se copian).
- El docente que actúa lo resuelve `CurrentUser::teacherId()`; los Services lo reciben por argumento.

## Formato del archivo

| Aspecto | Regla |
|---|---|
| Extensión | `.csv` o `.xlsx` (sin distinguir mayúsculas). Cualquier otra se rechaza |
| Tamaño | Hasta **10 MB** |
| Filas | Hasta **2000** filas de datos, sin contar el encabezado. Configurable con `SCIEM_NOMINA_MAX_FILAS`. En CSV cuentan las filas con contenido; en XLSX se comprueba antes de cargar el archivo, con la última fila declarada de la hoja que más filas tenga |
| Encabezados | En la primera fila, sin distinguir mayúsculas: `Estudiante`, `Apellidos`, `Nombres`. Las demás columnas se ignoran, en cualquier orden |
| CSV: separador | `,` o `;`, detectado por la primera línea. Se acepta BOM de UTF-8 |
| CSV: codificación | **UTF-8** o **Windows-1252** (lo habitual al exportar desde Excel o WebSIS); si no es UTF-8 válido se convierte desde Windows-1252. Un archivo con caracteres de control (binario, UTF-16, una imagen renombrada) se rechaza |
| XLSX | Se lee la hoja activa. Los códigos numéricos conservan su formato, así que un SIS con ceros a la izquierda no los pierde |
| Código SIS | Solo dígitos, de **8 a 12** (`SCIEM_ESTUDIANTE_COD_SIS_MIN` y `_MAX`). Se normaliza: recorta, colapsa espacios (incluido el espacio duro de Excel) y pasa a mayúsculas |
| Nombres y apellidos | Hasta 50 caracteres los nombres y 30 los apellidos |
| Filas vacías | Se omiten y no cuentan para el tope |

Hay una plantilla descargable en `/plantillas/nomina-plantilla.csv`.

El **CI no se pide ni se genera**: los estudiantes nacen con `ci = NULL` y el control de ingreso lo captura en la primera verificación.

## POST …/nomina/preview

No escribe nada: guarda las filas leídas bajo un **token** y devuelve su clasificación.

**200**

```json
{
  "data": {
    "token": "9f2c…(64 caracteres hexadecimales)",
    "total_filas": 3,
    "filas_validas": 2,
    "filas_inconsistentes": 1,
    "filas": [
      { "numero_fila": 2, "codigo_sis": "300000001", "apellidos": "MUÑOZ SOLIZ", "nombres": "JOSÉ", "estado": "new_student", "errores": [] },
      { "numero_fila": 3, "codigo_sis": "300000002", "apellidos": "PEÑA ROJAS", "nombres": "ANA", "estado": "already_enrolled", "errores": [] },
      { "numero_fila": 4, "codigo_sis": null, "apellidos": "SIN CODIGO", "nombres": "MARIA", "estado": "inconsistent", "errores": ["missing_sis_code"] }
    ]
  }
}
```

El token **vive 15 minutos**, es de **un solo uso**, y queda atado al docente y al grupo con que se generó. `filas_validas` cuenta también a quienes ya están inscritos; para saber cuántos se incorporarán hay que contar los estados `new_student` y `existing_student`.

### Estados de fila

| `estado` | Significado | ¿Se incorpora? |
|---|---|---|
| `new_student` | El código SIS no existe: se crea el estudiante y se inscribe | Sí |
| `existing_student` | El estudiante existe pero no está en este grupo: se inscribe | Sí |
| `already_enrolled` | Ya tiene una fila en la nómina de este grupo (el estado de esa fila no se lee) | No, no se duplica |
| `inconsistent` | La fila tiene errores, listados en `errores` | No |

### Códigos de error de fila

| Código | Causa |
|---|---|
| `missing_sis_code` | Falta el código SIS |
| `missing_last_names` | Faltan los apellidos |
| `missing_first_names` | Faltan los nombres |
| `sis_code_not_numeric` | El código SIS tiene caracteres que no son dígitos |
| `sis_code_invalid_length` | El código SIS está fuera de 8 a 12 dígitos |
| `last_names_too_long` | Más de 30 caracteres en los apellidos |
| `first_names_too_long` | Más de 50 caracteres en los nombres |
| `duplicate_row_in_file` | El código SIS se repite con datos idénticos: vale la primera fila y las demás se reportan como repetidas |
| `conflicting_duplicate_in_file` | El código SIS se repite con datos distintos: no se puede saber cuál es la correcta, ninguna se importa |

### Errores del preview

| Estado | `message` | Cuándo |
|---|---|---|
| 422 | `Debe seleccionar una nómina.` | Falta el campo `archivo` |
| 422 | `La nómina debe enviarse como archivo.` | `archivo` no es un archivo |
| 422 | `El archivo debe tener formato CSV o XLSX.` | Otra extensión |
| 422 | `La nómina no puede superar los 10 MB.` | Archivo de más de 10 MB |
| 422 | `No se pudo recibir la nómina. Verifique que no supere los 10 MB y vuelva a intentarlo.` | PHP descartó el archivo (más de 12 MB) |
| 413 | `La nómina no puede superar los 10 MB.` | El cuerpo superó `post_max_size` (16 MB) |
| 422 | `El archivo CSV está vacío.` / `El archivo CSV no contiene encabezados.` | CSV sin contenido |
| 422 | `El archivo CSV no es un archivo de texto. Guárdelo como CSV con codificación UTF-8 y vuelva a cargarlo.` | Contenido binario |
| 422 | `El archivo XLSX está vacío.` / `No se pudo procesar el archivo XLSX.` | XLSX vacío o corrupto |
| 422 | `Falta la columna requerida: estudiante.` (o `apellidos`, `nombres`) | Falta un encabezado |
| 422 | `La nómina no contiene estudiantes.` | Solo hay encabezado |
| 422 | `La nómina supera el máximo de 2000 filas por archivo.` | Más filas que el tope |
| 422 | `El identificador del grupo no es válido.` y afines | `id_grupo` no numérico, menor que 1 o mayor que 2147483647 |
| 403 | `Un auxiliar no puede cargar la nómina de un grupo.` | Cuenta con rol Auxiliar |
| 403 | `Solo el docente que dicta el grupo puede cargar su nómina.` | Grupo de otro docente |
| 404 | `No existe el grupo indicado.` | El grupo no existe |
| 422 | `El grupo no está activo.` | Grupo inactivo |
| 422 | `El grupo no pertenece al período académico activo.` | Grupo de otro período |
| 422 | `La nómina no puede modificarse mientras un examen del grupo está en ingreso o en curso.` | Nómina congelada |
| 500 | `No hay un período académico activo configurado. Defina SCIEM_PERIODO_ACTIVO_ID…` | Error de despliegue |

## POST …/nomina/confirm

Cuerpo: `{ "token": "<64 caracteres hexadecimales>" }`. Vuelve a clasificar las filas dentro de una transacción con el grupo bloqueado, inserta por lotes y consume el token.

**200**

```json
{
  "data": {
    "total_filas": 3,
    "filas_inconsistentes": 1,
    "estudiantes_creados": 1,
    "estudiantes_inscritos": 1,
    "ya_inscritos": 1
  }
}
```

`estudiantes_inscritos` incluye a los recién creados. Las cifras son las reales: si entre el preview y la confirmación otra carga inscribió o creó a alguien, esa fila cuenta como `ya_inscritos` o se reutiliza, sin fallar.

| Estado | `message` | Cuándo |
|---|---|---|
| 404 | `El preview de la nómina no existe o ha expirado.` | Token inexistente, vencido o ya usado |
| 403 | `El preview no pertenece al docente actual.` | Token de otro docente |
| 422 | `El preview no corresponde al grupo indicado.` | Token de otro grupo |
| 422 | `El token del preview no es válido.` | Formato distinto de 64 hexadecimales |

También aplican los errores 403, 404 y 422 de acceso al grupo de la tabla anterior.

## Comportamiento aditivo

La carga **agrega**; nunca reemplaza ni quita:

- Los estudiantes que ya están en el grupo y no aparecen en el archivo **se conservan**.
- Un estudiante ya inscrito no se duplica ni se vuelve a inscribir: subir de nuevo la misma nómina da `estudiantes_inscritos: 0` y `ya_inscritos` con todos.
- Una inscripción existente nunca se reactiva ni se modifica, sea cual sea su estado.
- Un estudiante que existe en otro grupo se **reutiliza**: no se crea otro con el mismo código SIS (`estudiante.cod_sis` es único). Si dos docentes importan al mismo estudiante nuevo a la vez, uno lo crea y el otro lo reutiliza.
- La nómina de un grupo nunca toca la de otro: el token lleva el grupo y la confirmación solo inscribe en él.

## Costo

Confirmar cuesta un número fijo de consultas, no una por estudiante: unas 10 para 50 filas y unas 12 para el tope de 2000.
