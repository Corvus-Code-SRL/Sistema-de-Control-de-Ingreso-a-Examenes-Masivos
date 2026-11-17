# API de estudiantes excepcionales (HU-023)

Permite al **Docente responsable** de un examen **En curso** agregar a la lista de ese examen a un estudiante que no figura en ella (convalidación, inscripción tardía aprobada). El estudiante queda **solo en ese examen**, con la marca «Excepcional» y el motivo; no entra al padrón del grupo ni a ningún otro examen.

Convenciones: prefijo `/api`, cabecera `Accept: application/json`, token de Sanctum. Las respuestas correctas llevan `data` (y `mensaje` al crear); los errores llevan `message` (y `errors` por campo en los 422). Middlewares: `auth:sanctum`, `account.active`, `password.changed`.

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/docente/estudiantes-excepcionales/examenes` | Exámenes En curso del docente (el desplegable) |
| GET | `/api/docente/examenes/{id_examen}/estudiantes-excepcionales/opciones` | Grupos, carreras y ambientes de ese examen |
| GET | `/api/docente/examenes/{id_examen}/estudiantes-excepcionales/buscar?cod_sis=` | Busca al estudiante por código SIS |
| POST | `/api/docente/examenes/{id_examen}/estudiantes-excepcionales` | Lo agrega a la lista del examen |

`{id_examen}` es de 1 a 9 dígitos; otro formato, o un examen que no existe, responde **404**.

## Quién puede usarla

- La capacidad `special_cases` de `config/sciem.php` (`casos-especiales.gestionar` en el cliente) es solo del **Docente**. Un docente **Invitado**, un Auxiliar, un Administrador, una cuenta sin rol o deshabilitada recibe **403** («Solo un docente puede registrar estudiantes excepcionales.»); sin token, **401**.
- Además, el examen debe ser **del docente responsable** (`examen.id_usuario_docente`). Un colaborador que aceptó una invitación, o cualquier otro docente, recibe **403** en opciones, búsqueda y registro, y el examen no aparece en su lista.

## Reglas

- **Solo mientras el examen está EN_CURSO.** PROGRAMADO (aunque el ingreso esté abierto), FINALIZADO o CANCELADO responden **409** («El examen ya no está en curso: no se pueden registrar estudiantes excepcionales.»). El estado se vuelve a comprobar dentro de la transacción, con el examen bloqueado.
- **El estudiante debe existir, estar ACTIVO y tener código SIS.** El código SIS tiene 8 o 9 dígitos (si no, **422**).
- **Sin duplicados:** si ya figura en la nómina de los grupos del examen o ya fue registrado como excepcional, responde **422** («Ya figura en la lista de estudiantes de este examen») y no se crea una segunda fila. La base lo respalda con la clave primaria `(id_examen, id_estudiante)`.
- **Grupo, carrera y ambiente** deben pertenecer al examen: el grupo a `grupo_examen`, la carrera a la oferta de ese grupo (`grupo_oferta`) y el ambiente a `examen_ambiente`; si no, **422**. El grupo es en el que se **contará** al estudiante en el examen.
- **Motivo** obligatorio, de 5 a 255 caracteres.
- Registrarlo **no cambia el ambiente de ningún otro estudiante**: el reparto automático de aulas solo considera la nómina y el excepcional conserva el ambiente que se eligió.
- Después se comporta como cualquier estudiante del examen: el control de ingreso lo encuentra por SIS o CI y lo verifica, ingresa, se cuenta en los totales, en el historial y en el reporte final; si nunca ingresa, queda como **ausente**.
- Queda en la bitácora (`REGISTRAR_ESTUDIANTE_EXCEPCIONAL`) con el docente, el examen, el estudiante y el motivo. La acción debe estar sembrada (`ActionSeeder`).

## GET /api/docente/estudiantes-excepcionales/examenes

Exámenes En curso del docente, del más próximo al más lejano. Sin exámenes responde **200** con `data: []`.

```json
{ "data": [ { "id_examen": 7, "nombre_examen": "Parcial de Cálculo II", "fecha": "2026-11-20", "hora_inicio": "08:00", "estado": "EN_CURSO" } ] }
```

## GET /api/docente/examenes/{id_examen}/estudiantes-excepcionales/opciones

```json
{
  "data": {
    "grupos": [ { "id_grupo": 11, "nombre": "Grupo 1" } ],
    "carreras": [ { "id_grupo": 11, "id_carrera": 1, "nombre": "Ingeniería de Sistemas" } ],
    "ambientes": [ { "id_ambiente": 21, "nombre": "691A", "capacidad": 40, "ocupados": 12 } ]
  }
}
```

La carrera solo se pregunta al docente si el grupo ofrece más de una.

## GET /api/docente/examenes/{id_examen}/estudiantes-excepcionales/buscar

| Parámetro | Regla |
|---|---|
| `cod_sis` | Obligatorio; 8 o 9 dígitos |

```json
{ "data": { "id_estudiante": 55, "cod_sis": "202600777", "nombre_completo": "Ana Quispe Mamani", "carrera": { "id_carrera": 1, "nombre": "Ingeniería de Sistemas" }, "ya_en_examen": false } }
```

No devuelve el CI. Un código que no existe responde **404** («No se encontró a ningún estudiante con este código»).

## POST /api/docente/examenes/{id_examen}/estudiantes-excepcionales

```json
{ "id_estudiante": 55, "id_grupo": 11, "id_carrera": 1, "id_ambiente": 21, "motivo": "Convalidación aprobada por la Dirección de Carrera" }
```

Responde **201**:

```json
{
  "data": { "id_examen": 7, "id_estudiante": 55, "cod_sis": "202600777", "nombre_completo": "Ana Quispe Mamani", "carrera": { "id_carrera": 1, "nombre": "Ingeniería de Sistemas" }, "motivo_excepcional": "Convalidación aprobada por la Dirección de Carrera", "fecha_registro": "2026-11-17T09:00:00-04:00" },
  "mensaje": "Estudiante excepcional registrado."
}
```

Si el control de ingreso del examen está abierto, el estudiante se suma al estado operativo de Redis sin reconstruirlo; si Redis falla, el registro ya está confirmado en PostgreSQL y el error queda en el log.

## Dónde se ve la marca «Excepcional»

Los listados del examen traen `es_excepcional` y `motivo_excepcional`: la verificación del control de ingreso, la lista **En curso**, y el reporte final (pantalla, PDF y Excel).

## Datos

Una fila de `examen_estudiante` con `es_excepcional = true`: `id_grupo` queda en NULL y el grupo en `id_grupo_excepcional` (más `id_carrera_excepcional`, `id_usuario_docente_excepcional` y `motivo_excepcional`). `id_grupo_efectivo` (columna generada) es el grupo con el que se cuenta al estudiante. `fk_examen_estudiante_grupo_estudiante` se conserva sin cambios. Ver «Versión 18» de `docs/database/creation-script.sql`.
