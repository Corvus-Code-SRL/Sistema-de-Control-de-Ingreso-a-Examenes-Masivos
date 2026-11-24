# HU-031 — Escenario QA: distribución opcional de habilitados por ambiente

Este documento no ejecuta migraciones, seeders ni cambios de datos. Preparar el escenario solo en
`postgres/sciem_test` (o en una base local propia) y verificar antes la configuración efectiva. La suite
backend reconstruye `sciem_test`: no ejecutarla mientras se revisa manualmente este escenario. No usar
`sciem_db` ni Supabase.

## Modelo

La distribución es **opcional** y es una **referencia** para el control de ingreso: si un estudiante se
presenta en otro ambiente o en uno lleno, el sistema **avisa y deja registrar de todos modos**. Nunca rechaza.
Sin distribución guardada, o después de «Dejar de usar la distribución», todos entran por cualquier ambiente
sin aviso.

## Datos a preparar mediante los mecanismos existentes

- Un creador Docente, un colaborador con invitación aceptada y un Docente ajeno al examen.
- Examen PROGRAMADO con control sin abrir, en un horario válido para la apertura local.
- Dos ambientes (por ejemplo 691A y 691B) con capacidad suficiente para todos los habilitados.
- Estudiantes de los grupos del examen: la mayoría habilitados y al menos uno `NO_HABILITADO`. Apellidos
  variados, con tildes y ñ. Al menos un estudiante en dos grupos del examen (se cuenta una sola vez).

No se añade un seeder: los helpers `SeedsRoomDistribution` y las pruebas Feature preparan estos escenarios
de forma determinista en la base de pruebas.

## Recorrido esperado (CA-01 a CA-12)

| CA | Verificación |
|---|---|
| CA-01 | «Distribuir habilitados» está en el menú lateral y lista los exámenes PROGRAMADO del Docente y sus exámenes EN_CURSO **que tienen distribución guardada**, sin pasar por el detalle del examen. |
| CA-02 | Con un solo ambiente todos los habilitados quedan en él y se muestra un mensaje. Con 2 o más ambientes la distribución está disponible pero no es obligatoria. Sin distribución guardada, cualquier habilitado entra por cualquier ambiente sin aviso. |
| CA-03 | Solo los habilitados reciben ambiente. Los `NO_HABILITADO` no aparecen en la lista de la distribución. |
| CA-04 | Con más habilitados que plazas totales, guardar se bloquea y la vista indica cuántos lugares faltan. |
| CA-05 | «Distribuir por apellido» ordena por apellido y reparte en **partes iguales**; si un ambiente no admite su parte, el excedente pasa a los demás sin superar ninguna capacidad. Ejemplos: 100 estudiantes en ambientes de 60 y 60 → 50/50; en ambientes de 80 y 40 → 60/40. |
| CA-06 | Mover un estudiante a un ambiente con espacio funciona. A un ambiente lleno: «El ambiente ya está lleno» y no cambia nada. |
| CA-07 | El resumen por ambiente (asignados / capacidad) se actualiza en cada cambio. |
| CA-08 | No se guarda nada hasta pulsar «Guardar distribución». Salir con cambios pendientes pide confirmación (recarga, cierre de pestaña, enlaces y botón «atrás» del navegador); si el Docente sale, se conserva la distribución anterior. |
| CA-09 | Una vez abierto el control de ingreso (`ingreso_abierto_en`) crear o modificar responde 409 y la vista queda en «Solo lectura». Desde ese momento **solo el creador** puede pulsar «Dejar de usar la distribución» y confirmar; colaboradores y Docentes ajenos reciben 403. La decisión es **irreversible** (una segunda llamada responde 409). |
| CA-10 | Un estudiante habilitado después de guardar aparece como «Sin ambiente asignado» y puede entrar por cualquier ambiente mientras tanto. |
| CA-11 | En el ingreso normal y en el ingreso con retraso (HU-037), un estudiante en un ambiente distinto del asignado ve «Este estudiante tiene asignado el ambiente X». «Cancelar» no guarda nada; «Registrar de todos modos» guarda el ambiente **real**, sin incidencia ni registro en la Central de Riesgo. Si el ambiente llegó a su capacidad, también avisa y deja registrar. |
| CA-12 | El guardado es atómico. La bitácora registra el guardado (por estudiante, ambiente anterior y nuevo), «Dejar de usar la distribución» y cada ingreso fuera del ambiente asignado (ambiente asignado y real). |

## Casos de borde

1. **Excepcional (HU-023).** Se registra con el control ya abierto, cuando la distribución es solo lectura.
   Nunca forma parte de la distribución: no aparece en su lista, no suma en la capacidad ni figura como «Sin
   ambiente asignado». Su ambiente asignado en el ingreso es el que se eligió al registrarlo, y si se presenta
   en otro recibe el mismo aviso.
2. **Dejar de usar con controladores en la página.** Tras confirmarlo, la siguiente verificación de cualquier
   estudiante ya no muestra aviso de ambiente, sin recargar el control de ingreso.
3. **Edición concurrente.** Con dos sesiones del creador, el guardado con una versión obsoleta responde 409 y
   conserva el borrador hasta recargar explícitamente.
4. **Ambiente retirado o capacidad reducida antes de abrir.** Las asignaciones válidas se conservan y las que
   dejan de caber quedan «Sin ambiente asignado». Abrir el control ya no falla por capacidad: avisa al registrar.
5. **Sin distribución.** Repetir un ingreso en un examen sin distribución guardada y comprobar que no hay aviso
   por ambiente distinto.
