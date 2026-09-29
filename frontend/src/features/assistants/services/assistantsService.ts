import { apiClient, apiPut } from '@/lib/api-client'
import type { ExamAssistant, ExamAssistantsData } from '../types/assistant.types'

interface DataResponse<TData> {
  data: TData
  mensaje?: string
}

export const assistantsService = {
  async listExamAssistants(examId: number, signal?: AbortSignal): Promise<ExamAssistantsData> {
    const response = await apiClient<DataResponse<ExamAssistantsData>>(
      `/examenes/${examId}/auxiliares`,
      { signal }
    )
    return response.data
  },

  async assignClassroom(examId: number, userId: string, classroomId: number): Promise<ExamAssistant> {
    const response = await apiPut<DataResponse<ExamAssistant>>(
      `/examenes/${examId}/auxiliares/${userId}/ambiente`,
      { id_ambiente: classroomId }
    )
    return response.data
  },
}