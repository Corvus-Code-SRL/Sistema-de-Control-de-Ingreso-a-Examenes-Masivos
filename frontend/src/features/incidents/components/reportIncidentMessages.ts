import type { SituationExam } from '../types/exam-situation.types'
import { REPORT_INCIDENT_FLOW, type ReportIncidentFlow } from '../types/incident.types'

/** Textos del acceso «Reportar incidencia» del menú (HU-042). */
export const REPORT_INCIDENT_MESSAGES = {
  emptyTitle: 'No tiene exámenes en curso para reportar incidencias',
  emptyDescription:
    'Las incidencias se reportan solo mientras el examen está en curso. Cuando inicie uno aparecerá aquí.',
  noStudents: 'No hay estudiantes presentes para este filtro.',
  sent: 'Enviado a revisión del docente',
  alreadyReported: 'Este intento ya fue reportado.',
} as const

/** Avisos del reporte de un intento cuando el envío no se confirma o el flujo ya no está abierto. */
export const ATTEMPT_REPORT_ERRORS: Record<ReportIncidentFlow, { unconfirmed: string; closed: string }> = {
  [REPORT_INCIDENT_FLOW.ENTRY_CONTROL]: {
    unconfirmed: 'No se pudo confirmar el envío. Revise los últimos rechazos antes de reintentar.',
    closed: 'El control de ingreso ya no está disponible. Actualice la pantalla.',
  },
  [REPORT_INCIDENT_FLOW.LATE_ENTRY]: {
    unconfirmed: 'No se pudo confirmar el envío. Vuelva a verificar al estudiante antes de reintentar.',
    closed: 'El ingreso con retraso ya no está disponible. Actualice la pantalla.',
  },
}

export const REPORT_INCIDENT_STATE_LABELS: Record<SituationExam['estado'], string> = {
  EN_INGRESO: 'En ingreso',
  EN_CURSO: 'En curso',
}
