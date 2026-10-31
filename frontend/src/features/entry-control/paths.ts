/** Rutas de las vistas del ingreso con retraso (HU-037). */
export const LATE_ENTRY_EXAMS_PATH = '/retrasos'
export const LATE_ENTRY_PATH = '/examenes/:examId/retrasos'

export function lateEntryPath(examId: number): string {
  return `/examenes/${examId}/retrasos`
}

/** Vista En curso (HU-038-A) a la que vuelve el ingreso con retraso. */
export function inProgressPath(examId: number): string {
  return `/examenes/${examId}/en-curso`
}
