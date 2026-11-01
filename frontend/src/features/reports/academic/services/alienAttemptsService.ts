import { apiClient } from '@/lib/api-client'
import type {
  AlienAttemptsFilters,
  AlienAttemptsListResponse,
  AlienAttemptsPage,
} from '../types/alienAttempts.types'

export const ALIEN_ATTEMPTS_PER_PAGE = 10

/**
 * Filas del reporte, agrupadas por estudiante y ordenadas por intentos descendente.
 *
 * El backend resuelve el filtro por facultad del examen, el rango de fechas y el
 * agrupamiento. La vista solo manda lo que el usuario eligió.
 */
export async function getAlienAttempts(
  { desde, hasta, facultad, page }: AlienAttemptsFilters,
  signal?: AbortSignal
): Promise<AlienAttemptsPage> {
  const response = await apiClient<AlienAttemptsListResponse>('/reportes/intentos-ajenos', {
    query: {
      desde,
      hasta,
      facultad: facultad ?? undefined,
      page,
      per_page: ALIEN_ATTEMPTS_PER_PAGE,
    },
    signal,
  })

  return {
    items: response.data,
    stats: {
      intentos: response.meta.total_intentos,
      estudiantes: response.meta.total_estudiantes,
      enCentralRiesgo: response.meta.total_en_central_riesgo,
    },
    page: response.meta.current_page,
    perPage: response.meta.per_page,
    total: response.meta.total,
    totalPages: Math.max(1, response.meta.last_page),
  }
}

/** El PDF se pide como archivo: mismo filtro, distinto Accept. */
export function getAlienAttemptsPdf(
  { desde, hasta, facultad }: Omit<AlienAttemptsFilters, 'page'>,
  signal?: AbortSignal
): Promise<Blob> {
  return apiClient<Blob>('/reportes/intentos-ajenos/pdf', {
    query: {
      desde,
      hasta,
      facultad: facultad ?? undefined,
    },
    responseType: 'blob',
    signal,
  })
}
