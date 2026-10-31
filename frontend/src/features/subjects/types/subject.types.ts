import type { CollectionResponse, Page } from '@/types/api.types'

/** Carrera a la que pertenece un par materia-carrera. */
export interface Career {
  id_carrera: number
  nombre: string
  codigo: string
  id_facultad: number
}

/**
 * Par materia-carrera: la unidad real del catálogo, nunca la materia sola.
 *
 * Una misma materia existe en varias carreras, así que `carrera` es obligatoria
 * para poder distinguir dos entradas con el mismo nombre.
 */
export interface SubjectCareer {
  id_materia: number
  id_carrera: number
  nombre: string
  codigo: string
  descripcion: string | null
  /** `materia_carrera.nivel_semestre` es varchar(15), no un número. */
  nivel_semestre: string | null
  obligatoria: boolean | null
  /** Activa solo si lo están la materia y el par; si no, no es seleccionable. */
  activa: boolean
  /** El docente dicta al menos un grupo del par en el periodo activo. */
  es_mia: boolean
  cantidad_grupos: number
  carrera: Career
}

export interface SubjectCatalogMeta {
  total: number
  total_mias: number
  id_periodo_activo: number
  /** Etiqueta del período activo («2/2026», «Verano 2026»); `null` si no hay un período activo. */
  nombre_periodo_activo: string | null
}

/** Respuesta cruda de `GET /materias`. */
export type SubjectCatalogResponse = CollectionResponse<SubjectCareer, SubjectCatalogMeta>

/** Una página del catálogo, ya normalizada para la vista. */
export interface SubjectCatalogPage extends Page<SubjectCareer> {
  meta: SubjectCatalogMeta
  /** Mensaje del backend cuando el catálogo institucional está vacío. */
  mensaje: string | null
}

/**
 * Clave estable de un par materia-carrera.
 *
 * La tabla tiene clave primaria compuesta: ningún identificador por separado
 * sirve como `key` de React ni para comparar dos entradas.
 */
export function subjectCareerKey(pair: Pick<SubjectCareer, 'id_carrera' | 'id_materia'>): string {
  return `${pair.id_carrera}-${pair.id_materia}`
}

// HU-006 (catálogo de solo lectura y asignaciones)

/** Fila del catálogo administrativo: código, nombre y estado. */
export interface AdminSubjectSummary {
  id_materia: number
  nombre: string
  codigo: string
  estado: string
}

export interface SubjectData {
  id_materia: number
  nombre: string
  codigo: string
  descripcion: string | null
  estado: string
}

/** Un período académico (gestion_periodo) tal como lo entrega `GET /periodos`. */
export interface AcademicPeriod {
  id_periodo: number
  /** Etiqueta UMSS lista para mostrar: «1/2026», «2/2026», «0/2026». */
  etiqueta: string
  nombre_periodo: string
  gestion: number
  vigente: boolean
}

export interface AcademicPeriodsResponse {
  data: AcademicPeriod[]
  meta: { id_periodo_activo: number | null }
}

export interface SubjectCareerAssignmentPayload {
  id_materia: number
  id_periodo: number
}

/** Una oferta académica: un par materia-carrera ofrecido en un período. `estado` es el de la oferta. */
export interface SubjectCareerAssignment {
  id_carrera: number
  id_materia: number
  id_periodo: number
  estado: string
  carrera: Career
  materia: SubjectData
  periodo: AcademicPeriod
}

export interface SubjectCareerAssignmentResponse {
  data: SubjectCareerAssignment
  mensaje: string
}

export interface AdminCareersResponse {
  data: Career[]
}

export interface AssignableSubjectsResponse {
  data: SubjectData[]
}

export interface SubjectCareerAssignmentsResponse {
  data: SubjectCareerAssignment[]
  /**
   * `carreras`: todas las activas más las inactivas con ofertas en el período, por nombre, sin
   * importar el filtro pedido.
   */
  meta: { carreras: Career[]; periodo: AcademicPeriod }
}

/** Ofertas del período más las carreras que alimentan el selector del filtro. */
export interface SubjectCareerAssignmentsResult {
  assignments: SubjectCareerAssignment[]
  careers: Career[]
}
