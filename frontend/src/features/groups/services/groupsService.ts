import { apiClient } from '@/lib/api-client'
import type {
  CreateGroupPayload,
  GroupDetail,
  GroupDetailResponse,
  GroupMutationResponse,
  SubjectGroups,
  SubjectGroupsResponse,
} from '../types/group.types'

/**
 * Grupos de un par materia-carrera.
 *
 * Devuelve todos los grupos del par, incluidos los de otros docentes: el
 * listado es del par, no del docente, y cada grupo ya viene marcado con `es_mio`.
 */
export async function getGroupsByPair(
  careerId: number,
  subjectId: number,
  signal?: AbortSignal
): Promise<SubjectGroups> {
  const response = await apiClient<SubjectGroupsResponse>(
    `/carreras/${careerId}/materias/${subjectId}/grupos`,
    { signal }
  )

  return {
    subject: response.data.materia,
    groups: response.data.grupos,
    meta: response.meta,
  }
}

/** Un grupo por sí solo, con el par al que pertenece. */
export async function getGroup(groupId: number, signal?: AbortSignal): Promise<GroupDetail> {
  const response = await apiClient<GroupDetailResponse>(`/grupos/${groupId}`, { signal })

  return {
    group: response.data.grupo,
    subject: response.data.materia,
    meta: response.meta,
  }
}

/**
 * Los errores de duplicidad (CA 8) y de materia inactiva/ajena (CA 7) llegan
 * como ApiError 422/404 respectivamente; el llamador los distingue con
 * `error.isValidation` / `error.isNotFound`.
 */
export async function createGroup(
  payload: CreateGroupPayload,
  signal?: AbortSignal
): Promise<GroupMutationResponse['data']> {
  const response = await apiClient<GroupMutationResponse>('/grupos', {
    method: 'POST',
    body: payload,
    signal,
  })

  return response.data
}