import { apiClient, apiPost } from '@/lib/api-client'
import type { PageRequest } from '@/types/api.types'
import type {
  AcademicPeriod,
  AcademicPeriodsResponse,
  AdminCareersResponse,
  AdminSubjectSummary,
  AssignableSubjectsResponse,
  SubjectCareerAssignment,
  SubjectCareerAssignmentPayload,
  SubjectCareerAssignmentResponse,
  SubjectCareerAssignmentsResponse,
  SubjectCareerAssignmentsResult,
  SubjectCatalogPage,
  SubjectCatalogResponse,
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
 * Catálogo institucional de materias para Administración (solo lectura).
 *
 * El backend consulta directamente `materia`, por lo que cada materia aparece
 * una sola vez aunque todavía no tenga una carrera asociada. `search` filtra por
 * código o nombre; el servidor ignora mayúsculas y tildes.
 */
export async function getAdminSubjects(
  search = '',
  signal?: AbortSignal
): Promise<AdminSubjectSummary[]> {
  const term = search.trim()

  const response = await apiClient<{ data: AdminSubjectSummary[] }>(
    '/materias/administracion',
    { query: { q: term === '' ? undefined : term }, signal }
  )

  return response.data
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
 * Períodos académicos disponibles (activos), del más reciente al más antiguo.
 *
 * TODO(Sprint 4): features/groups tiene su propia copia (periodsService); mover ambas a un módulo
 * compartido cuando el selector de período deje de ser de dos features.
 */
export async function getAcademicPeriods(signal?: AbortSignal): Promise<AcademicPeriod[]> {
  const response = await apiClient<AcademicPeriodsResponse>('/periodos', { signal })

  return response.data
}

/**
 * Obtiene las materias activas que la carrera todavía no ofrece en el período.
 */
export async function getAssignableSubjects(
  careerId: number,
  periodId: number,
  signal?: AbortSignal
): Promise<AssignableSubjectsResponse['data']> {
  const response = await apiClient<AssignableSubjectsResponse>(
    `/administracion/carreras/${careerId}/materias-asignables`,
    { query: { id_periodo: periodId }, signal }
  )

  return response.data
}

/**
 * Ofrece una materia existente a una carrera en un período.
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

/**
 * Ofertas del período, de cualquier estado, de la asignada más recientemente a la más antigua; el
 * orden lo fija el servidor y no se reordena aquí. Con `careerId` el servidor solo devuelve las de
 * esa carrera; `careers` trae siempre las del selector del período.
 */
export async function getSubjectCareerAssignments(
  periodId: number,
  careerId: number | null = null,
  signal?: AbortSignal
): Promise<SubjectCareerAssignmentsResult> {
  const response = await apiClient<SubjectCareerAssignmentsResponse>(
    '/administracion/asignaciones',
    { query: { id_periodo: periodId, id_carrera: careerId ?? undefined }, signal }
  )

  return { assignments: response.data, careers: response.meta.carreras }
}
