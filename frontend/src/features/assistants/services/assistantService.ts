import { apiClient, apiPost } from '@/lib/api-client'
import type {
  Assistant,
  AssistantListResponse,
  AssistantWithGroups,
  AssistantWithGroupsListResponse,
  MoveAssistantPayload,
  GroupOption,
} from '../types/assistant.types'

interface GroupOptionListResponse {
  data: GroupOption[]
}

/** Auxiliares ya incorporados a algún grupo del docente en el período activo. */
export async function getMyAssistants(signal?: AbortSignal): Promise<AssistantWithGroups[]> {
  const response = await apiClient<AssistantWithGroupsListResponse>(
    '/docente/auxiliares',
    { signal }
  )
  return response.data
}

/** Búsqueda por SIS o nombre. */
export async function searchAssistants(
  criterio: string,
  signal?: AbortSignal
): Promise<Assistant[]> {
  const response = await apiClient<AssistantListResponse>('/docente/auxiliares/buscar', {
    signal,
    query: { criterio },
  })
  return response.data
}

/** Incorpora un auxiliar a un grupo. */
export async function addAssistantToGroup(
  groupId: number,
  idUsuario: string
): Promise<void> {
  await apiPost(`/docente/grupos/${groupId}/auxiliares`, { id_usuario: idUsuario })
}

/** Habilita un auxiliar para un examen. */
export async function enableAssistantForExam(
  examId: number,
  idUsuario: string
): Promise<void> {
  await apiPost(`/docente/examenes/${examId}/auxiliares`, { id_usuario: idUsuario })
}

/** Quita un auxiliar de un grupo (soft delete en backend). */
export async function removeAssistantFromGroup(
  groupId: number,
  idUsuario: string
): Promise<void> {
  await apiClient(`/docente/grupos/${groupId}/auxiliares/${idUsuario}`, {
    method: 'DELETE',
  })
}

/** Quita un auxiliar de un examen (delete real en backend). */
export async function removeAssistantFromExam(
  examId: number,
  idUsuario: string
): Promise<void> {
  await apiClient(`/docente/examenes/${examId}/auxiliares/${idUsuario}`, {
    method: 'DELETE',
  })
}

/**
 * Mueve un auxiliar de un grupo a otro.
 *
 * Es una operación compuesta: quita del origen y añade al destino. El backend
 * no tiene un endpoint específico para esto, así que el frontend hace las dos
 * peticiones en secuencia.
 */
export async function moveAssistantBetweenGroups(
  payload: MoveAssistantPayload
): Promise<void> {
  await removeAssistantFromGroup(payload.id_grupo_origen, payload.id_usuario)
  await addAssistantToGroup(payload.id_grupo_destino, payload.id_usuario)
}

/**
 * Grupos del docente en el período activo.
 *
 * Se usan para poblar los selectores de "Asignar auxiliar" y "Mover de grupo".
 */
export async function getMyGroups(signal?: AbortSignal): Promise<GroupOption[]> {
  const response = await apiClient<GroupOptionListResponse>('/docente/grupos', { signal })

  return response.data
}

export async function addAssistantToGroups(
  userId: string,
  groupIds: number[]
): Promise<void> {
  await apiPost(`/docente/auxiliares/${userId}/grupos`, { grupos: groupIds })
}