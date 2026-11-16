import { apiClient, apiPost } from '@/lib/api-client'
import type {
  CollaboratorCandidate,
  InvitableExam,
  SendInvitationPayload,
  SentInvitation,
} from '../types/collaborator.types'

interface DataResponse<TData> {
  data: TData
  mensaje?: string
}

/** Invitaciones a docentes colaboradores (HU-026). Todas las rutas son del docente autenticado. */
export const collaboratorsService = {
  /** Exámenes PROGRAMADOS propios: los únicos a los que todavía se puede invitar. */
  async listInvitableExams(signal?: AbortSignal): Promise<InvitableExam[]> {
    const response = await apiClient<DataResponse<InvitableExam[]>>('/docente/invitaciones/examenes', { signal })

    return response.data
  },

  /** Docentes activos con grupos de la materia del examen en el período vigente, sin el propio docente. */
  async searchTeachers(examId: number, criteria: string, signal?: AbortSignal): Promise<CollaboratorCandidate[]> {
    const response = await apiClient<DataResponse<CollaboratorCandidate[]>>(
      `/docente/invitaciones/examenes/${examId}/docentes`,
      { query: { criterio: criteria }, signal }
    )

    return response.data
  },

  /** Envía la invitación; queda PENDIENTE. 409 si ya hay una vigente o el examen dejó de estar PROGRAMADO. */
  async sendInvitation(examId: number, payload: SendInvitationPayload): Promise<SentInvitation> {
    const response = await apiPost<DataResponse<SentInvitation>>(`/docente/invitaciones/examenes/${examId}`, payload)

    return response.data
  },

  /** Invitaciones enviadas por el docente, de la más reciente a la más antigua. */
  async listSentInvitations(signal?: AbortSignal): Promise<SentInvitation[]> {
    const response = await apiClient<DataResponse<SentInvitation[]>>('/docente/invitaciones', { signal })

    return response.data
  },
}
