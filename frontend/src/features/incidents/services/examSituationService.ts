import { apiClient, apiPost } from '@/lib/api-client'
import type {
  SituationCreated, SituationInput, SituationPage, SituationExam, SituationExamsPage,
  SituationWorkspace, SituationWorkspaceFilters,
} from '../types/exam-situation.types'

/** Registro, historial y contexto de la HU-057 mediante el cliente autenticado compartido. */
export const examSituationService = {
  exams(state: SituationExam['estado'], page: number, signal?: AbortSignal): Promise<SituationExamsPage> {
    return apiClient('/situaciones/examenes', { query: { estado: state, page }, signal })
  },

  context(examId: number, filters: SituationWorkspaceFilters, signal?: AbortSignal) {
    return apiClient<{ data: SituationWorkspace }>(`/situaciones/examenes/${examId}/contexto`, {
      query: { ...filters }, signal,
    })
  },

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