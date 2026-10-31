import { apiClient } from '@/lib/api-client'
import type { FinalReport } from '../types/finalReport.types'

interface DataResponse<T> {
  data: T
}

/** Generar el PDF tarda más que una consulta normal, que espera 15 s. */
const PDF_TIMEOUT_MS = 60_000

export const finalReportService = {
  async getFinalReport(examId: number, signal?: AbortSignal): Promise<FinalReport> {
    const response = await apiClient<DataResponse<FinalReport>>(
      `/examenes/${examId}/reporte-final`,
      { signal }
    )
    return response.data
  },

  async downloadPdf(examId: number, signal?: AbortSignal): Promise<Blob> {
    return apiClient<Blob>(`/examenes/${examId}/reporte-final/pdf`, {
      responseType: 'blob',
      timeoutMs: PDF_TIMEOUT_MS,
      signal
    })
  }
}
