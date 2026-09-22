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
