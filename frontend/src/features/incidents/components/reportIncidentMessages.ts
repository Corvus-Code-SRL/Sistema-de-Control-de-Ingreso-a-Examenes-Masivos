import type { SituationExam } from '../types/exam-situation.types'

/** Textos del acceso «Reportar incidencia» del menú (HU-042). */
export const REPORT_INCIDENT_MESSAGES = {
  emptyTitle: 'No tiene exámenes en curso para reportar incidencias',
  emptyDescription:
    'Las incidencias se reportan solo mientras el examen está en curso. Cuando inicie uno aparecerá aquí.',
  noStudents: 'No hay estudiantes presentes para este filtro.',
  sent: 'Enviado a revisión del docente',
} as const

export const REPORT_INCIDENT_STATE_LABELS: Record<SituationExam['estado'], string> = {
  EN_INGRESO: 'En ingreso',
  EN_CURSO: 'En curso',
}
