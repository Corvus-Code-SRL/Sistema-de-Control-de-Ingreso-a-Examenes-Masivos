import type { ExamInProgress } from '@/features/exams'
import { apiClient, apiPost } from '@/lib/api-client'
import type { IncidentReport, IncidentReportInput, IncidentType } from '../types/incident.types'

interface DataResponse<T> { data: T }

export const incidentService = {
  async examContext(examId: number, signal?: AbortSignal): Promise<ExamInProgress> {
    const response = await apiClient<DataResponse<ExamInProgress>>(`/examenes/${examId}/en-curso`, { signal })
    return response.data
  },

  async reportDuringExam(
    examId: number, studentId: number, roomId: number, input: IncidentReportInput,
  ): Promise<IncidentReport> {
    const body = new FormData()
    body.append('id_tipo_falta', input.incidentTypeId)
    body.append('descripcion', input.description.trim())
    body.append('id_ambiente', String(roomId))
    body.append('retirar', input.retire ? '1' : '0')
    if (input.evidence) body.append('evidencia', input.evidence)
    const response = await apiPost<DataResponse<IncidentReport>>(
      `/examenes/${examId}/estudiantes/${studentId}/incidencias`, body,
    )
    return response.data
  },

  async types(signal?: AbortSignal): Promise<IncidentType[]> {
    const response = await apiClient<DataResponse<IncidentType[]>>('/tipos-falta', { signal })
    return response.data
  },

  async report(examId: number, attemptId: number, input: IncidentReportInput): Promise<IncidentReport> {
    const body = new FormData()
    body.append('id_tipo_falta', input.incidentTypeId)
    body.append('descripcion', input.description.trim())
    if (input.evidence) body.append('evidencia', input.evidence)

    const response = await apiPost<DataResponse<IncidentReport>>(
      `/control-ingreso/examenes/${examId}/intentos/${attemptId}/reporte`,
      body,
    )
    return response.data
  },
}