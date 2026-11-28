import { apiClient, apiPost } from '@/lib/api-client'
import type {
  UnassignableSubjectsRequest,
  UnassignableSubjectsResponse,
  UnassignSubjectPayload,
  UnassignSubjectResult,
} from '../types/unassignSubject.types'

export const UNASSIGN_PER_PAGE = 7

const PATH = '/catalogo/desvincular-materia'

/** HU-008: ofertas de una carrera en un período, con conteo de grupos. Búsqueda y paginación son del servidor. */
export async function getUnassignableSubjects(
  { careerId, periodId, search, page, perPage }: UnassignableSubjectsRequest,
  signal?: AbortSignal
): Promise<UnassignableSubjectsResponse> {
  const term = search.trim()

  const response = await apiClient<Partial<UnassignableSubjectsResponse>>(PATH, {
    query: {
      carrera: careerId,
      periodo: periodId,
      buscar: term === '' ? undefined : term,
      page,
      per_page: perPage,
    },
    signal,
  })

  return {
    materias: response.materias ?? [],
    total: response.total ?? 0,
    page: response.page ?? page,
    per_page: response.per_page ?? perPage,
  }
}

/** HU-008: la única escritura de la vista. El servidor cambia la oferta a INACTIVO; no la borra. */
export async function unassignSubject(
  payload: UnassignSubjectPayload,
  signal?: AbortSignal
): Promise<UnassignSubjectResult> {
  return apiPost<UnassignSubjectResult>(PATH, payload, { signal })
}
