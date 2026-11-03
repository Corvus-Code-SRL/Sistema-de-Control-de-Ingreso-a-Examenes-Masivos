/** Rutas de las vistas del ingreso con retraso (HU-037). */
export const LATE_ENTRY_EXAMS_PATH = '/retrasos'
export const LATE_ENTRY_PATH = '/examenes/:examId/retrasos'

export function lateEntryPath(examId: number): string {
  return `/examenes/${examId}/retrasos`
}
