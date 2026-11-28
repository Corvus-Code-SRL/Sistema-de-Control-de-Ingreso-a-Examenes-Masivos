/** Estado de la oferta tal como lo envía el backend. La UI muestra `INACTIVO` como «Desvinculada». */
export type OfferingStatus = 'ACTIVO' | 'INACTIVO'

export interface UnassignableSubject {
  id_materia: number
  codigo: string
  nombre: string
  estado: OfferingStatus
  /** Grupos ACTIVOS de la materia en la carrera y el período. */
  grupos_count: number
}

export interface UnassignableSubjectsResponse {
  materias: UnassignableSubject[]
  total: number
  page: number
  per_page: number
}

export interface UnassignableSubjectsRequest {
  careerId: number
  periodId: number
  search: string
  page: number
  perPage: number
}

export interface UnassignSubjectPayload {
  id_carrera: number
  id_periodo: number
  id_materia: number
}

export interface UnassignSubjectResult {
  id_carrera: number
  id_materia: number
  id_periodo: number
  estado: OfferingStatus
  grupos_count: number
}

/** HU-008: reactivar usa el mismo cuerpo que desvincular. */
export type ReactivateSubjectPayload = UnassignSubjectPayload

export type ReactivateSubjectResult = UnassignSubjectResult