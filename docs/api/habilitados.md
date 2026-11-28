# Carga de la lista de habilitados (HU-028)

Contrato de HU-028. La vista solo **carga** el archivo con los estudiantes habilitados y lo compara con la
nómina del examen. No existe edición manual por estudiante: el endpoint `PUT /api/examenes/{exam}/habilitados`
se eliminó y responde 405.

## Acceso y formato

Todas las rutas están bajo `/api` y requieren sesión, cuenta activa y la capacidad `eligible_list`
(Docente o Invitado según `config('sciem.access')`; en la interfaz, `examenes.cargar-habilitados`).
Un rol sin la capacidad recibe 403, incluso si es el creador del examen. Se usa `Accept: application/json`.

Solo el **creador** del examen puede leer y cargar la lista. Un colaborador con invitación aceptada, o un
docente ajeno, recibe 403.

| Método | Ruta | HTTP de éxito | Cuerpo |
|---|---|---|---|
| GET | `/api/examenes/habilitados` | 200 | `{ data: ExamOption[] }` |
| GET | `/api/examenes/{exam}/habilitados` | 200 | `{ data: { editable, estado, estudiantes[] } }` |
| POST | `/api/examenes/{exam}/habilitados/preview` | 200 | `{ data: Preview }` (multipart, campo `archivo`) |
| POST | `/api/examenes/{exam}/habilitados/confirm` | 200 | `{ data: Resultado, mensaje }` (JSON, campo `token`) |

`ExamOption` (selector): `id_examen`, `nombre`, `materia`, `carrera`, `grupos[]`, `fecha`, `hora_inicio` (hora local
de La Paz), `estado` y `ambientes[]` (`id`, `nro_aula`, ordenados por número de aula; vacío si no tiene).

## Ventana de escritura

El selector solo devuelve los exámenes del docente en estado PROGRAMADO con el control de ingreso sin abrir
(`ingreso_abierto_en` nulo). Fuera de esa ventana, `preview` y `confirm` responden **409**. `GET` sigue
disponible en cualquier estado con `editable: false`.

## Archivo

Se acepta CSV o XLSX de hasta 10 MB. La plantilla es la misma de la nómina (HU-021),
`/plantillas/nomina-plantilla.csv`: columnas `Estudiante`, `Apellidos`, `Nombres` y, opcional, `Carrera`.

**Se compara solo por código SIS (`Estudiante`).** Apellidos, Nombres y Carrera son referenciales. Se reutiliza el
lector y el analizador de la nómina, de modo que las mismas reglas de formato aplican (por ejemplo, apellidos de
hasta 30 caracteres y nombres de hasta 50).

## Preview (`POST .../preview`)

Analiza el archivo y no toca la base de datos. Devuelve `token`, `total_filas`, `filas_validas`,
`filas_inconsistentes`, `faltantes` (estudiantes de la nómina que no figuran en el archivo) y `filas[]`, cada una con
`estado`: `matched`, `not_found_in_exam` o `inconsistent`.

Una fila `not_found_in_exam` es un **aviso**: el código SIS no pertenece al examen. Se muestra en el preview y se
ignora al confirmar.

## Confirmación (`POST .../confirm`)

En **una sola transacción**, con la fila del examen bloqueada y la ventana de escritura comprobada de nuevo:

- los estudiantes de la nómina que figuran en el archivo quedan `HABILITADO`;
- los que no figuran quedan `NO_HABILITADO` con el motivo fijo `NO_FIGURA_EN_LISTA` («No figura en la lista cargada»);
- los `CASO_ESPECIAL` no se modifican;
- un archivo vacío deja a toda la nómina como `NO_HABILITADO`.

Respuesta: `total_filas`, `filas_inconsistentes`, `no_encontrados`, `habilitados`, `total_no_habilitados`, `motivo` y
`no_habilitados[]` (`id_estudiante`, `codigo_sis`, `nombre_completo`). La operación se registra en la bitácora con la
acción `GUARDAR_HABILITADOS` y `origen: ARCHIVO`.

## Lista (`GET .../habilitados`)

Devuelve **toda** la nómina del examen en una sola respuesta, ordenada por apellidos y nombres, sin paginación. La
interfaz la muestra en un contenedor con scroll. Cada estudiante trae `estado_habilitacion` (`HABILITADO`,
`NO_HABILITADO` o `CASO_ESPECIAL`), `motivo` y `detalle`. Los motivos manuales guardados antes de retirar la edición
manual (`ASISTENCIA_INSUFICIENTE`, `NO_ENTREGO_TRABAJO_FINAL`, `NO_CUMPLE_PRERREQUISITO`, `OTRO: detalle`) se siguen
devolviendo hasta la próxima carga. La interfaz no muestra `motivo` ni `detalle`; se conservan para uso futuro.

## Efecto en otras vistas

- El control de ingreso rechaza a los `NO_HABILITADO`.
- Los `NO_HABILITADO` no cuentan como ausentes en los reportes.
- La distribución por ambiente (HU-031) solo asigna a los habilitados.
- Sin fila en `examen_estudiante`, un estudiante de la nómina cuenta como `HABILITADO`.
