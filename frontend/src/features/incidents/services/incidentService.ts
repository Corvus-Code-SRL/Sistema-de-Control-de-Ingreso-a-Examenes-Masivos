import type { ExamInProgress } from '@/features/exams'
import { apiClient, apiPost } from '@/lib/api-client'
import type { SituationExamsPage, SituationWorkspace, SituationWorkspaceFilters } from '../types/exam-situation.types'
import {
  REPORT_INCIDENT_FLOW, type IncidentReport, type IncidentReportInput, type IncidentType, type ReportIncidentFlow,
} from '../types/incident.types'

interface DataResponse<T> { data: T }

/** Ruta del reporte de un intento rechazado según el flujo que lo registró. */
const ATTEMPT_REPORT_PATH: Record<ReportIncidentFlow, (examId: number, attemptId: number) => string> = {
  [REPORT_INCIDENT_FLOW.ENTRY_CONTROL]: (examId, attemptId) =>
    `/control-ingreso/examenes/${examId}/intentos/${attemptId}/reporte`,
  [REPORT_INCIDENT_FLOW.LATE_ENTRY]: (examId, attemptId) =>
    `/control-ingreso/examenes/${examId}/retrasos/intentos/${attemptId}/reporte`,
}

/** Campos comunes de todo reporte: tipo de falta, descripción y evidencia opcional. */
function reportBody(input: IncidentReportInput): FormData {
  const body = new FormData()
  body.append('id_tipo_falta', input.incidentTypeId)
  body.append('descripcion', input.description.trim())
  if (input.evidence) body.append('evidencia', input.evidence)
  return body
}

export const incidentService = {
  /** Exámenes En curso donde puede reportar; endpoint propio de HU-042. */
  exams(page: number, signal?: AbortSignal): Promise<SituationExamsPage> {
    return apiClient('/incidencias/examenes', { query: { page }, signal })
  },

  /** Estudiantes presentes del examen, con búsqueda por SIS, nombre o CI. */
  workspace(examId: number, filters: SituationWorkspaceFilters, signal?: AbortSignal) {
    return apiClient<DataResponse<SituationWorkspace>>(`/incidencias/examenes/${examId}/contexto`, {
      query: { ...filters }, signal,
    })
  },

  async examContext(examId: number, signal?: AbortSignal): Promise<ExamInProgress> {
    const response = await apiClient<DataResponse<ExamInProgress>>(`/examenes/${examId}/en-curso`, { signal })
    return response.data
  },

  async reportDuringExam(
    examId: number, studentId: number, roomId: number, input: IncidentReportInput,
  ): Promise<IncidentReport> {
    const body = reportBody(input)
    body.append('id_ambiente', String(roomId))
    body.append('retirar', input.retire ? '1' : '0')
    const response = await apiPost<DataResponse<IncidentReport>>(
      `/examenes/${examId}/estudiantes/${studentId}/incidencias`, body,
    )
    return response.data
  },

  async types(signal?: AbortSignal): Promise<IncidentType[]> {
    const response = await apiClient<DataResponse<IncidentType[]>>('/tipos-falta', { signal })
    return response.data
  },

  async report(
    examId: number,
    attemptId: number,
    input: IncidentReportInput,
    flow: ReportIncidentFlow = REPORT_INCIDENT_FLOW.ENTRY_CONTROL,
  ): Promise<IncidentReport> {
    const response = await apiPost<DataResponse<IncidentReport>>(
      ATTEMPT_REPORT_PATH[flow](examId, attemptId),
      reportBody(input),
    )
    return response.data
  },
}
