/** Detalle único del examen, con un origen opcional para regresar al grupo. */
export function examDetailPath(examId: number, groupId?: number): string {
  const path = `/examenes/${examId}`
  return groupId === undefined ? path : `${path}?grupo=${groupId}`
}
/** Reporte final de un examen finalizado (HU-051). */
export function examFinalReportPath(examId: number): string {
  return `/examenes/${examId}/reporte-final`
}
