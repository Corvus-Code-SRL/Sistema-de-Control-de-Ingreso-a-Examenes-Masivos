# HU-028 — Escenario QA: cargar la lista de estudiantes habilitados

Este documento no ejecuta migraciones, seeders ni cambios de datos. Preparar el escenario solo en
`postgres/sciem_test` (o en una base local propia) y verificar antes la configuración efectiva. La suite
backend reconstruye `sciem_test`: no ejecutarla mientras se revisa manualmente este escenario. No usar
`sciem_db` ni Supabase.

## Modelo

La vista **solo carga** el archivo con los estudiantes habilitados y lo compara con la nómina del examen.
No hay casillas, ni «No habilitar», ni botón de guardar: la lista queda definida por el archivo. Quien no figura en
el archivo queda «No habilitado» (el motivo «No figura en la lista cargada» se guarda, pero la interfaz no lo muestra). Se compara solo por código SIS.

## Datos a preparar mediante los mecanismos existentes

- Un creador Docente (y otro con rol Invitado), un colaborador con invitación aceptada y un rol sin la capacidad
  (Auxiliar).
- Un examen PROGRAMADO con el control de ingreso sin abrir y una nómina de 6 estudiantes.
- Un examen con el control ya abierto (`ingreso_abierto_en` no nulo).

## Recorrido esperado

| # | Verificación |
|---|---|
| 1 | «Cargar lista de habilitados» aparece en el menú lateral para Docente e Invitado, no para Auxiliar ni Administrador. |
| 2 | Sin exámenes disponibles se muestra un estado vacío, sin botones hacia otras vistas. |
| 3 | El selector «Examen» (ancho completo, placeholder «Seleccione un examen») solo ofrece exámenes PROGRAMADO con el control sin abrir. |
| 4 | Al elegir un examen aparece debajo una tarjeta con materia, grupos, fecha, hora de inicio (HH:MM, hora de La Paz) y ambientes («Sin ambientes» si no tiene); el examen elegido queda en `?examen=ID`. |
| 5 | La página no tiene sección de plantilla. El botón «Cargar lista de habilitados» abre un panel con el mismo título, el enlace «Descargar plantilla» (con ícono) y la nota «Se compara por código SIS; los demás datos son referenciales.». La plantilla descargada, subida sin cambios, pasa el análisis y el preview. |
| 6 | Se llena la plantilla con 4 de los 6 estudiantes y 1 código SIS ajeno: el preview marca 4 coincidencias, 1 «no encontrado en el examen» (aviso) y 2 faltantes. |
| 7 | Al confirmar quedan 4 habilitados y 2 «No habilitado» con «No figura en la lista cargada». El código ajeno se ignora. |
| 8 | Los totales (en la nómina, habilitados, no habilitados) se ven sobre la lista. La tabla muestra Código SIS, Estudiante, Grupo y Estado, sin columna «Motivo». |
| 9 | La lista completa está en un contenedor con scroll y cabecera fija (sin paginación); el buscador y los filtros Todos/Habilitados/No habilitados trabajan sobre toda la lista. |
| 10 | Con el control de ingreso, un «No habilitado» es rechazado con su motivo. |
| 11 | Volver a cargar el archivo con los 6 deja a todos habilitados. |
| 12 | Con el control ya abierto, el preview y la confirmación responden 409 y la vista queda en solo lectura. |
| 13 | Un colaborador y un docente ajeno reciben 403. |
| 14 | `PUT /api/examenes/{id}/habilitados` responde 405. |
| 15 | Un estudiante deshabilitado a mano antes de este cambio sigue «No habilitado» hasta la próxima carga. Su motivo anterior ya no se muestra en la interfaz, pero la API lo sigue devolviendo. |
| 16 | Los «No habilitados» no cuentan como ausentes en el reporte final. |
