import { apiClient } from '@/lib/api-client'
import type { FinalReport } from '../types/finalReport.types'

interface DataResponse<T> {
  data: T
}

export const finalReportService = {
  async getFinalReport(examId: number, signal?: AbortSignal): Promise<FinalReport> {
    const response = await apiClient<DataResponse<FinalReport>>(
      `/examenes/${examId}/reporte-final`,
      { signal }
    )
    return response.data
  }
}
