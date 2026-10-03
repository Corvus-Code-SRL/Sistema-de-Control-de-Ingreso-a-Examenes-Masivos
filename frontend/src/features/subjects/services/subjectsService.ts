import { apiClient, apiPost, apiPut } from '@/lib/api-client'
import type { PageRequest } from '@/types/api.types'
import type {
  AdminCareersResponse,
  AdminSubjectSummary,
  AssignableSubjectsResponse,
  MateriaFormState,
  SubjectCareerAssignmentPayload,
  SubjectCareerAssignmentResponse,
  SubjectCatalogPage,
  SubjectCatalogResponse,
  UpdateSubjectPayload,
  UpdateSubjectResponse,
} from '../types/subject.types'

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

/**
 * Catálogo institucional de materias para Administración.
 *
 * El backend consulta directamente `materia`, por lo que cada materia aparece
 * una sola vez aunque todavía no tenga una carrera asociada.
 */
export async function getAdminSubjects(
  signal?: AbortSignal
): Promise<AdminSubjectSummary[]> {
  const response = await apiClient<{ data: AdminSubjectSummary[] }>(
    '/materias/administracion',
    { signal }
  )

  return response.data
}

/* ==========================================================================
    Flujo legado: Registrar Materia
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

/* ==========================================================================
   HU-06 (Asignar Materia a Carrera)
   ========================================================================== */

/**
 * Obtiene las carreras activas disponibles para Administración.
 */
export async function getAdminCareers(
  signal?: AbortSignal
): Promise<AdminCareersResponse['data']> {
  const response = await apiClient<AdminCareersResponse>(
    '/administracion/carreras',
    { signal }
  )

  return response.data
}

/**
 * Obtiene las materias activas que todavía no están vinculadas a la carrera.
 */
export async function getAssignableSubjects(
  careerId: number,
  signal?: AbortSignal
): Promise<AssignableSubjectsResponse['data']> {
  const response = await apiClient<AssignableSubjectsResponse>(
    `/administracion/carreras/${careerId}/materias-asignables`,
    { signal }
  )

  return response.data
}

/**
 * Asigna una materia existente a una carrera.
 */
export async function assignSubjectToCareer(
  careerId: number,
  payload: SubjectCareerAssignmentPayload,
  signal?: AbortSignal
): Promise<SubjectCareerAssignmentResponse> {
  return apiPost<SubjectCareerAssignmentResponse>(
    `/administracion/carreras/${careerId}/materias`,
    payload,
    { signal }
  )
}

/* ==========================================================================
   HU-007 (Editar Materia)
   ========================================================================== */

/**
 * Actualiza únicamente el nombre y código de una materia existente.
 */
export async function actualizarMateria(
  idMateria: number,
  data: UpdateSubjectPayload
): Promise<UpdateSubjectResponse> {
  return apiPut<UpdateSubjectResponse>(
    `/materias/${idMateria}`,
    data
  )
}
