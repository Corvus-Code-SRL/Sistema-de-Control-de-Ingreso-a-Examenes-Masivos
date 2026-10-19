/* =========================================================================
 * HU-045 — Revisar incidencias con doble comprobación
 * ========================================================================= */

export type IncidentSource = 'estudiante'
export type IncidentState = 'PENDIENTE' | 'APROBADO' | 'RECHAZADO'
export type IncidentFilter = 'pendiente' | 'revisada'

export interface IncidentPerson {
  nombre_completo: string
  codigo: string
  grupo: string | null
  carrera: string | null
  foto_url: string | null
}

export interface IncidentExam {
  id_examen: number
  nombre_examen: string
  fecha: string
  materia: string | null
}

export interface IncidentTypeFault {
  id: number
  nombre: string
}

export interface IncidentReporter {
  nombre_completo: string
  rol: string
}

export interface IncidentReviewer {
  nombre_completo: string
  fecha_revision: string
  observacion_revision: string | null
}

export interface IncidentClassroom {
  id_ambiente: number
  nro_aula: string
}

export interface IncidentAttempt {
  id_intento: number
  motivo: string
  motivo_legible: string
  observacion: string | null
  fecha_intento: string
  hora_intento: string
  ambiente: IncidentClassroom | null
}

export interface IncidentEvidence {
  nombre: string
  tipo: string
  tamano_bytes: number | null
  disponible: boolean
  url: string
}

/** DTO completo de una incidencia en la vista de revisión (HU-045). */
export interface IncidentReview {
  id: string
  tipo: IncidentSource
  estado: IncidentState
  persona: IncidentPerson
  examen: IncidentExam
  tipo_falta: IncidentTypeFault
  descripcion: string | null
  evidencia_url: string | null
  evidencia: IncidentEvidence | null
  reportante: IncidentReporter
  fecha_reporte: string
  hora_reporte: string
  ambiente: IncidentClassroom | null
  intento: IncidentAttempt | null
  revisor: IncidentReviewer | null
  en_central_riesgo: boolean
}

export interface IncidentListResponse {
  data: IncidentReview[]
  meta: {
    total_pendientes: number
    total_revisadas: number
  }
}

export interface IncidentDetailResponse {
  data: IncidentReview
}
export interface IncidentType {
  id_falta: number
  nombre: string
  descripcion: string | null
}

export interface ReportIncidentContext {
  examId: number
  examName: string
  roomId: number
  attemptId: number
  student: { id_estudiante: number; cod_sis: string; nombre_completo: string }
  roomName: string
  reason: string
  timeLabel?: string
}

export interface IncidentReportInput {
  incidentTypeId: string
  description: string
  evidence: File | null
}

export interface IncidentReport {
  id_reporte_est: number
  id_intento: number
  estado: 'PENDIENTE'
}

export type IncidentFormErrors = Partial<Record<'id_tipo_falta' | 'descripcion' | 'evidencia', string>>
