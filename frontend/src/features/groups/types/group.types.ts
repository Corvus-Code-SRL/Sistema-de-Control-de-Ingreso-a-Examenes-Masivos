import type { SubjectCareer } from '@/features/subjects'
import type { ResourceResponse } from '@/types/api.types'

/** Periodo académico al que pertenece un grupo. */
export interface Period {
  id_periodo: number
  nombre_periodo: string
  /** `periodo.gestion` es smallint: el año. */
  gestion: number
}

export interface GroupTeacher {
  nombre_completo: string
}

/**
 * Grupo de un par materia-carrera.
 *
 * Es la única vía por la que un docente queda vinculado a una materia, así que
 * un grupo siempre trae a su docente dueño, sea o no quien consulta.
 */
export interface Group {
  id_grupo: number
  /** `grupo.num_grupo` es varchar(5): «1», «2A»… nunca un número. */
  num_grupo: string
  /** `grupo.gestion` es varchar(10) y no coincide con `periodo.gestion`, que es el año. */
  gestion: string
  activo: boolean
  /** El grupo lo dicta quien consulta; solo entonces puede abrirse su detalle. */
  es_mio: boolean
  /** Inscripciones activas. Cero significa, exactamente, que no hay nómina. */
  cantidad_estudiantes: number
  docente: GroupTeacher
  periodo: Period
}

export interface SubjectGroupsMeta {
  total: number
  total_mios: number
  id_periodo_activo: number
}

/** Respuesta de `GET /carreras/{id_carrera}/materias/{id_materia}/grupos`. */
export type SubjectGroupsResponse = ResourceResponse<
  { materia: SubjectCareer; grupos: Group[] },
  SubjectGroupsMeta
>

export interface GroupDetailMeta {
  id_periodo_activo: number
  es_periodo_activo: boolean
}

/** Respuesta de `GET /grupos/{id_grupo}`. */
export type GroupDetailResponse = ResourceResponse<
  { grupo: Group; materia: SubjectCareer },
  GroupDetailMeta
>

/** Grupos de un par, con el par ya resuelto. */
export interface SubjectGroups {
  subject: SubjectCareer
  groups: Group[]
  meta: SubjectGroupsMeta
}

/** Un grupo del docente junto a la materia que dicta: la unidad de «Mis cursos». */
export interface Course {
  group: Group
  subject: SubjectCareer
}

/** Grupo abierto en el detalle, con su par y el periodo resuelto. */
export interface GroupDetail {
  group: Group
  subject: SubjectCareer
  meta: GroupDetailMeta
}

/**
 * Un grupo sin nómina es un grupo con cero inscritos: un solo estado, no dos.
 *
 * La regla vive aquí para que ninguna vista la reinvente comparando por su cuenta.
 */
export function hasRoster(group: Group): boolean {
  return group.cantidad_estudiantes > 0
}

/** Etiqueta del grupo tal como la nombra el docente: «Grupo 1». */
export function groupLabel(group: Group): string {
  return `Grupo ${group.num_grupo}`
}

/** Título completo de un curso: materia y grupo, que es como se identifica en el detalle. */
export function courseTitle(subject: SubjectCareer, group: Group): string {
  return `${subject.nombre} · ${groupLabel(group)}`
}

/**
 * solo pide N° de grupo y
 * Período académico; grupo.gestion (varchar) se deriva en el backend a partir
 * de periodo.gestion (smallint) del período elegido.
 */
export interface CreateGroupPayload {
  id_carrera: number
  id_materia: number
  num_grupo: string
  /** Editable por el Docente; si se omite, el backend asigna el período activo (CA 11). */
  id_periodo?: number
}

/**
 * Respuesta de crear/actualizar un grupo.
 *
 * No usa `ResourceResponse<TData, TMeta>` (de `types/api.types.ts`) a propósito:
 * ese tipo exige `meta` siempre, y `ApiResponse::created()`/`success()` del
 * backend no lo devuelve en estas operaciones — devuelve `{ data, message }`..
 */
export interface GroupMutationResponse {
  data: {
    grupo: Group
    materia: SubjectCareer
  }
  message: string
}

/** Un período disponible para el selector (`GET /api/periodos`). */
export interface PeriodOption {
  id_periodo: number
  nombre_periodo: string
  gestion: number
}