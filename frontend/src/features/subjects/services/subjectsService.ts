import { apiClient } from '@/lib/api-client'
import type { PageRequest } from '@/types/api.types'
import type { SubjectCatalogPage, SubjectCatalogResponse } from '../types/subject.types'

export const DEFAULT_PER_PAGE = 8

/**
 * Trae una página del catálogo institucional de materias.
 *
 * La paginación es del lado del servidor: una facultad puede tener cientos de
 * pares materia-carrera y la vista nunca los descarga todos.
 */
export async function getSubjectCatalog(
  { page, perPage }: PageRequest,
  signal?: AbortSignal
): Promise<SubjectCatalogPage> {
  const response = await apiClient<SubjectCatalogResponse>('/materias', {
    query: { page, per_page: perPage },
    signal,
  })

  return toPage(response, page, perPage)
}

/*
 * ---------------------------------------------------------------------------
 * Adaptación temporal
 * ---------------------------------------------------------------------------
 * `GET /materias` todavía ignora `page` y `per_page` y devuelve la colección
 * completa. Hasta que el backend aplique `paginate()`, la página se recorta
 * aquí para que el resto de la aplicación ya hable de páginas reales.
 *
 * Cuando el backend pagine, `meta` traerá `current_page` y `last_page`: este
 * bloque se reduce a leer esos campos y `slice` desaparece. Es el único punto
 * del frontend que conoce la diferencia.
 */
function toPage(
  response: SubjectCatalogResponse,
  page: number,
  perPage: number
): SubjectCatalogPage {
  const total = response.meta.total
  const start = (page - 1) * perPage
  const alreadyPaginated = response.data.length <= perPage && total > response.data.length

  return {
    items: alreadyPaginated ? response.data : response.data.slice(start, start + perPage),
    page,
    perPage,
    total,
    totalPages: Math.max(1, Math.ceil(total / perPage)),
    meta: response.meta,
    mensaje: response.mensaje ?? null,
  }
}
