export interface IncidentType {
  id_falta: number
  nombre: string
  descripcion: string | null
}

export interface ReportIncidentContext {
  examId: number
  examName: string
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