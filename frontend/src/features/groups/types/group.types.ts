import type { ExamStatus } from '@/features/exams'
import type { SubjectCareer } from '@/features/subjects'
import type { Page, ResourceResponse } from '@/types/api.types'

/**
 * Periodo académico al que pertenece un grupo (una fila de `gestion_periodo`).
 *
 * `etiqueta` es la forma estándar de la UMSS `{codigo}/{anio}` («1/2026», «3/2026») y es lo que se
 * muestra. `nombre_periodo` es el nombre del catálogo («Semestre 1», «Verano»): texto secundario
 * (un *tooltip*), nunca en lugar de la etiqueta. La interfaz no construye etiquetas.
 */
export interface Period {
  id_periodo: number
  /** Nombre del catálogo de períodos. */
  nombre_periodo: string
  /** Etiqueta estándar `{codigo}/{anio}`. */
  etiqueta: string
  /** Año de la gestión (`gestion.anio`). */
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
  /** Docente que actúa; `null` si el backend no lo puede resolver. */
  docente: { nombre_completo: string | null }
}

/** Respuesta de `GET /carreras/{id_carrera}/materias/{id_materia}/grupos`. */
export type SubjectGroupsResponse = ResourceResponse<
  { materia: SubjectCareer; grupos: Group[] },
  SubjectGroupsMeta
>

export interface GroupDetailMeta {
  id_periodo_activo: number
  es_periodo_activo: boolean
  /** Un examen del grupo está en ingreso o en curso: el grupo no se puede editar (HU-019). */
  edicion_bloqueada: boolean
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

/** Un grupo del docente junto a la materia que dicta: la unidad de «Mis grupos». */
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
  /** Editable por el Docente; si se omite, el backend asigna el período vigente (CA 11). */
  id_periodo?: number
}

/**
 * Solo el número de grupo es editable (HU-019). id_carrera, id_materia e id_usuario_docente son
 * inmutables, y el período tampoco cambia: solo el vigente admite escritura (HU-006).
 */
export interface UpdateGroupPayload {
  num_grupo: string
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
  mensaje?: string | null
}

/** Un período disponible para el selector (`GET /api/periodos`). */
export interface PeriodOption {
  id_periodo: number
  /** Nombre del catálogo («Semestre 1»): ayuda secundaria. */
  nombre_periodo: string
  /** Etiqueta estándar `{codigo}/{anio}`: es la que se muestra. */
  etiqueta: string
  gestion: number
  /** Es el período vigente (`gestion_periodo.vigente`); a lo sumo uno. */
  vigente: boolean
}

/** Un estudiante inscrito en el grupo (HU-020): una fila de la lista del detalle. */
export interface GroupStudent {
  id_estudiante: number
  cod_sis: string
  apellido_paterno: string
  apellido_materno: string | null
  nombre: string
}

/** Respuesta de `GET /grupos/{id_grupo}/estudiantes`. */
export interface GroupStudentsListResponse {
  data: GroupStudent[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    /** Filas que cumplen la búsqueda: es lo que se pagina. */
    total: number
    /** Todas las inscripciones activas, sin la búsqueda: es el total del encabezado. */
    total_inscritos: number
  }
}

/** Una página de estudiantes más el total de inscritos del grupo. */
export interface GroupStudentsPage extends Page<GroupStudent> {
  enrolledTotal: number
}

/** Filtros de la lista de estudiantes; viven en la URL (`?q=`, `?page=`). */
export interface GroupStudentFilters {
  q: string
  page: number
}

/** Datos del listado contextual; este endpoint no carga ambientes ni grupos. */
export interface GroupExam {
  id_examen: number
  nombre_examen: string
  fecha: string
  hora_inicio: string
  hora_fin: string | null
  estado: ExamStatus
}

export interface GroupAssistant {
  id_usuario: string
  nombre_completo: string
  cod_sis: string
  correo: string
  fecha_incorporacion: string
  examenes: {
    id_examen: number
    nombre_examen: string
    fecha: string
    estado: ExamStatus
  }[]
}
