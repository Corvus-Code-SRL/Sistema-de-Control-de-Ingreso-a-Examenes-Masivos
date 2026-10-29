import { apiClient, apiPost } from '@/lib/api-client'
import type { SituationCreated, SituationInput, SituationPage } from '../types/exam-situation.types'

/** Contrato propuesto de la HU-057; requiere los endpoints del backend. */
export const examSituationService = {
  create(examId: number, studentId: number, input: SituationInput): Promise<SituationCreated> {
    const body = {
      tipo: input.type,
      motivo: input.reason.trim(),
      ...(input.type === 'CAMBIO_AMBIENTE'
        ? { id_ambiente_destino: Number(input.destinationRoomId) } : {}),
    }
    return apiPost<SituationCreated>(`/examenes/${examId}/estudiantes/${studentId}/situaciones`, body)
  },

  list(examId: number, page: number, roomId?: number, signal?: AbortSignal): Promise<SituationPage> {
    const query = new URLSearchParams({ page: String(page) })
    if (roomId !== undefined) query.set('id_ambiente', String(roomId))
    return apiClient<SituationPage>(`/examenes/${examId}/situaciones?${query}`, { signal })
  },
}