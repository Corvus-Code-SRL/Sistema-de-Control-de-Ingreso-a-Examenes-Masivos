# HU-031 — Escenario QA local preparado, sin ejecutar

Este documento no ejecuta migraciones, seeders ni cambios de datos. Reservar exclusivamente
`postgres/sciem_test` para cualquier preparación de este escenario y verificar previamente la
configuración efectiva. La suite backend reconstruye esa base; no ejecutarla mientras se revisa
manualmente este escenario. No usar `sciem_db` ni Supabase.

## Datos a preparar mediante los mecanismos existentes

- Un creador Docente y un colaborador con invitación aceptada.
- Examen PROGRAMADO, control sin abrir, en un horario válido para la apertura local.
- Dos ambientes de 30 plazas cada uno.
- 58 estudiantes de los grupos del examen: 55 habilitados y 3 NO_HABILITADO.
- Apellidos variados, con tildes y ñ. Al menos un estudiante en dos grupos del examen para
  comprobar que se cuenta una sola vez.

No se añade un seeder: los helpers `SeedsRoomDistribution` y las nuevas pruebas Feature ya
preparan escenarios deterministas con estos mismos mecanismos en la base de pruebas.

## Recorrido esperado

1. Entrar como creador desde «Distribuir habilitados», después de «Cargar lista de habilitados».
2. Elegir el examen: 55 habilitados, 3 no habilitados y capacidad 60. Los NO_HABILITADO no
   deben aparecer en las tarjetas de estudiantes.
3. Solicitar la propuesta: 28/27, por bloques alfabéticos consecutivos. Mover un estudiante,
   comprobar los contadores, salir y cancelar la salida. Descartar y confirmar que nada persistió.
4. Volver a proponer y guardar. Recargar y comprobar que se conservan los ambientes confirmados.
5. En una segunda sesión cargar la misma versión; guardar un ajuste en la primera. El guardado
   obsoleto de la segunda debe devolver 409 y conservar su borrador hasta recargar explícitamente.
6. Antes de abrir, agregar dos habilitados con la nómina existente: el total de grupos será 60.
   La vista debe mostrar ambos «Sin ambiente asignado». No guardar otra distribución.
7. Abrir mediante el flujo existente. Los nuevos deben recibir plazas libres; los ambientes de
   los 55 anteriores deben mantenerse. Comprobar el rechazo al presentarse en otro ambiente y
   la coincidencia entre snapshot, registro calculado desde PostgreSQL e interfaz de examen en curso.
8. Consultar HU-031 con el control abierto y como colaborador: solo lectura, sin instrucciones
   de guardar ni promesas de una asignación futura. Intentar un PUT y verificar el rechazo de ventana.
9. En un escenario independiente, quitar una habilitación y retirar un ambiente solo planificado,
   con capacidad suficiente en el restante. Confirmar que las asignaciones válidas se conservan y
   las pendientes se reubican al abrir.
10. En otro escenario, reducir la capacidad después de guardar. Confirmar el rechazo preexistente
    de apertura si la capacidad es menor que toda la nómina. No puentear ese control manualmente:
    `RoomDistributionIntegrationTest` comprueba directamente que los excedentes reciben
    `AULA_NO_ASIGNADA` cuando se construye un snapshot con capacidad insuficiente.
11. Con un CASO ESPECIAL que ya tenga ambiente operativo en `examen_estudiante`, comprobar
    que prevalece sobre su planificación y reserva capacidad para el resto. No inventar un alta
    de HU-023: su servicio no existe en esta revisión; las pruebas usan fixtures compatibles con las FK.
12. Repetir el ingreso con un examen sin distribución guardada y comprobar el comportamiento previo.

El guardado vacío y el ambiente único se verifican en las pruebas automáticas. Las confirmaciones
vacías conservan la limitación de versión/marcador documentada en el contrato API.
