import { apiClient } from '@/lib/api-client'
import type {
  CreateGroupPayload,
  GroupDetail,
  GroupDetailResponse,
  GroupMutationResponse,
  GroupStudentFilters,
  GroupStudentsListResponse,
  GroupStudentsPage,
  SubjectGroups,
  SubjectGroupsResponse,
  UpdateGroupPayload,
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

/**
 * Actualiza los datos habilitados de un grupo existente.
 *
 * `payload` nunca incluye id_carrera/id_materia/id_usuario_docente: son
 * inmutables y el backend los ignora aunque se envíen.
 */
export async function updateGroup(
  groupId: number,
  payload: UpdateGroupPayload,
  signal?: AbortSignal
): Promise<GroupMutationResponse['data']> {
  const response = await apiClient<GroupMutationResponse>(`/grupos/${groupId}`, {
    method: 'PUT',
    body: payload,
    signal,
  })

  return response.data
}

/**
 * Estudiantes inscritos en el grupo, 50 por página y ordenados por apellido.
 *
 * La búsqueda sin tildes por nombre o código SIS la resuelve el servidor; la vista solo manda lo
 * que el docente escribió.
 */
export async function getGroupStudents(
  groupId: number,
  { q, page }: GroupStudentFilters,
  signal?: AbortSignal
): Promise<GroupStudentsPage> {
  const response = await apiClient<GroupStudentsListResponse>(`/grupos/${groupId}/estudiantes`, {
    query: { q: q === '' ? undefined : q, page },
    signal,
  })

  return {
    items: response.data,
    page: response.meta.current_page,
    perPage: response.meta.per_page,
    total: response.meta.total,
    totalPages: Math.max(1, response.meta.last_page),
    enrolledTotal: response.meta.total_inscritos,
  }
}
