import { apiClient, apiPost } from '@/lib/api-client'
import type { IncidentReport, IncidentReportInput, IncidentType } from '../types/incident.types'

interface DataResponse<T> { data: T }

export const incidentService = {
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