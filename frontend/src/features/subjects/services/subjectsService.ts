import { apiClient } from '@/lib/api-client'
import type { PageRequest } from '@/types/api.types'
import type { SubjectCatalogPage, SubjectCatalogResponse, MateriaFormState } from '../types/subject.types'

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
/* ==========================================================================
    HU-006 (Registrar Materia)
   ========================================================================== */

/**
 * Envía el formulario para registrar una nueva materia en el catálogo.
 */
export async function registrarMateria(data: MateriaFormState) {
  // Como el apiClient actual solo está tipado para GET, usamos fetch nativo.
  const response = await fetch('/api/materias', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    // Si Laravel devuelve error de validación (422), lo lanzamos para que la UI lo atrape
    const errorData = await response.json();
    throw errorData; 
  }

  return response.json();
}