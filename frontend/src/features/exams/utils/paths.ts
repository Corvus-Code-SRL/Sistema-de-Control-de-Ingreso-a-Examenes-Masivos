/** Detalle único del examen, con un origen opcional para regresar al grupo. */
export function examDetailPath(examId: number, groupId?: number): string {
  const path = `/examenes/${examId}`
  return groupId === undefined ? path : `${path}?grupo=${groupId}`
}

/** Reporte final de un examen finalizado (HU-051). */
export function examFinalReportPath(examId: number): string {
  return `/examenes/${examId}/reporte-final`
}

/** Acceso al catálogo de exámenes para la consulta de asistencia (HU-066). */
export const EXAM_ATTENDANCE_PATH = '/examenes/estudiantes-ingresados'
