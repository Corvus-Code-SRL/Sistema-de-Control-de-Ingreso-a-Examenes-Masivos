import type { Page } from '@/types/api.types'

/** Tipo de falta del catálogo compartido (GET /tipos-falta). */
export interface FaultType {
  id_falta: number
  nombre: string
}

/** Una fila del listado: un reporte aprobado de un estudiante. */
export interface RiskCenterRecordSummary {
  id_cr_estudiante: number
  estudiante: {
    id_estudiante: number
    nombre_completo: string
    cod_sis: string
  }
  tipo_falta: FaultType
  examen: {
    id_examen: number
    nombre: string
    materia: string | null
  }
  /** ISO 8601. */
  fecha_registro: string
}

/** La evidencia no se enlaza: se descarga con la sesión. `disponible` es false si el archivo falta. */
export interface RiskCenterEvidence {
  nombre_archivo: string
  tamano_bytes: number | null
  disponible: boolean
}

export interface RiskCenterRecordDetail {
  id_cr_estudiante: number
  estudiante: {
    id_estudiante: number
    nombre_completo: string
    cod_sis: string
  }
  tipo_falta: FaultType
  examen: {
    id_examen: number
    nombre: string
    materia: string | null
    /** Carrera del examen, no del estudiante (puede cursar varias). */
    carrera: string | null
    /** YYYY-MM-DD, sin zona. */
    fecha: string
  }
  /** Solo si el reporte nació de un intento de ingreso. */
  ambiente: {
    nro_aula: string | null
    hora_intento: string
  } | null
  descripcion: string | null
  reportante: { nombre_completo: string; fecha: string }
  aprobador: { nombre_completo: string; fecha: string } | null
  observacion: string | null
  fecha_registro: string
  evidencia: RiskCenterEvidence | null
}

export interface RiskCenterListResponse {
  data: RiskCenterRecordSummary[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type RiskCenterPage = Page<RiskCenterRecordSummary>

/** Filtros del listado; viven en la URL (`?q=`, `?tipo=`, `?page=`). */
export interface RiskCenterFilters {
  q: string
  tipo: number | null
  page: number
}
