import { apiClient, apiPost, apiPut } from '@/lib/api-client'
import type {
  Assistant,
  AssistantExam,
  AssistantListResponse,
  AssistantWithGroups,
  AssistantWithGroupsListResponse,
  ExamAssistant,
  ExamAssistantsData,
  GroupOption,
  MoveAssistantPayload,
} from '../types/assistant.types'

interface DataResponse<TData> {
  data: TData
  mensaje?: string
}

export const assistantsService = {
  /* ---- HU-09: ambiente del auxiliar por examen ---- */

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

  async listMyExams(signal?: AbortSignal): Promise<AssistantExam[]> {
    const response = await apiClient<DataResponse<AssistantExam[]>>('/auxiliar/examenes', { signal })
    return response.data
  },

  /* ---- HU-08: auxiliares del docente ---- */

  /** Auxiliares ya incorporados a algún grupo del docente en el período activo. */
  async listMine(signal?: AbortSignal): Promise<AssistantWithGroups[]> {
    const response = await apiClient<AssistantWithGroupsListResponse>('/docente/auxiliares', { signal })
    return response.data
  },

  /** Búsqueda por SIS o nombre. */
  async search(criteria: string, signal?: AbortSignal): Promise<Assistant[]> {
    const response = await apiClient<AssistantListResponse>('/docente/auxiliares/buscar', {
      signal,
      query: { criterio: criteria },
    })
    return response.data
  },

  /**
   * Grupos del docente en el período activo.
   *
   * Pueblan los selectores de "Asignar auxiliar" y "Mover de grupo".
   */
  async listMyGroups(signal?: AbortSignal): Promise<GroupOption[]> {
    const response = await apiClient<DataResponse<GroupOption[]>>('/docente/grupos', { signal })
    return response.data
  },

  /** Incorpora un auxiliar a un grupo. */
  async addToGroup(groupId: number, userId: string): Promise<void> {
    await apiPost<DataResponse<null>>(`/docente/grupos/${groupId}/auxiliares`, { id_usuario: userId })
  },

  /** Incorpora un auxiliar a varios grupos a la vez. */
  async addToGroups(userId: string, groupIds: number[]): Promise<void> {
    await apiPost<DataResponse<null>>(`/docente/auxiliares/${userId}/grupos`, { grupos: groupIds })
  },

  /** Habilita un auxiliar para un examen. */
  async enableForExam(examId: number, userId: string): Promise<void> {
    await apiPost<DataResponse<null>>(`/docente/examenes/${examId}/auxiliares`, { id_usuario: userId })
  },

  /** Quita un auxiliar de un grupo (soft delete en backend). */
  async removeFromGroup(groupId: number, userId: string): Promise<void> {
    await apiClient<DataResponse<null>>(`/docente/grupos/${groupId}/auxiliares/${userId}`, {
      method: 'DELETE',
    })
  },

  /** Quita un auxiliar de un examen (delete real en backend). */
  async removeFromExam(examId: number, userId: string): Promise<void> {
    await apiClient<DataResponse<null>>(`/docente/examenes/${examId}/auxiliares/${userId}`, {
      method: 'DELETE',
    })
  },

  /**
   * Mueve un auxiliar de un grupo a otro.
   *
   * Es una operación compuesta: quita del origen y añade al destino. El backend
   * no tiene un endpoint específico, así que se hacen las dos peticiones en secuencia.
   */
  async moveBetweenGroups(payload: MoveAssistantPayload): Promise<void> {
    await assistantsService.removeFromGroup(payload.id_grupo_origen, payload.id_usuario)
    await assistantsService.addToGroup(payload.id_grupo_destino, payload.id_usuario)
  },
}
