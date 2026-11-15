import type { FaultType } from './riskCenter.types'

/** Tipo de falta del catálogo de la rectificación (GET /rectificacion-riesgo/tipos-falta). */
export interface RectificationFaultType extends FaultType {
  descripcion: string | null
}

/** Una coincidencia de la búsqueda de estudiantes. */
export interface RectifiableStudent {
  id_estudiante: number
  nombre_completo: string
  cod_sis: string
  ci: string | null
}

export interface StudentSearchResult {
  students: RectifiableStudent[]
  /** Máximo de coincidencias que devuelve el servidor. */
  limit: number
  /** Hay más coincidencias de las mostradas: conviene afinar la búsqueda. */
  hasMore: boolean
}

export type RectificationState = 'Original' | 'Rectificado'

/** Un registro confirmado del estudiante, con su estado de rectificación (solo de esta vista). */
export interface RectifiableRecord {
  id_cr_estudiante: number
  /** ISO 8601: cuándo se confirmó el registro. */
  fecha_registro: string
  examen: {
    id_examen: number
    nombre: string
    materia: string | null
    /** YYYY-MM-DD, sin zona. */
    fecha: string
  }
  /** El tipo de falta vigente. */
  tipo_falta: FaultType
  aprobador: { nombre_completo: string; fecha: string } | null
  estado: RectificationState
  numero_rectificaciones: number
  /** ISO 8601 de la última rectificación; null si es «Original». */
  ultima_rectificacion: string | null
  /** Número de la versión vigente (1 si nunca se rectificó). */
  version_vigente: number
}

export interface StudentRecords {
  estudiante: RectifiableStudent
  registros: RectifiableRecord[]
}

export type VersionState = 'Vigente' | 'Anterior'

export interface RecordVersion {
  numero_version: number
  estado: VersionState
  tipo_falta: FaultType
  descripcion: string | null
  /** Nulo en la versión 1: es el valor original, sin rectificación. */
  motivo: string | null
  autor: { nombre_completo: string }
  /** ISO 8601. */
  fecha: string
}

export interface RectifiableRecordDetail extends Omit<RectifiableRecord, 'examen'> {
  estudiante: { id_estudiante: number; nombre_completo: string; cod_sis: string }
  examen: RectifiableRecord['examen'] & { carrera: string | null }
  ambiente: { nro_aula: string | null; hora_intento: string } | null
  /** La descripción vigente. */
  descripcion: string | null
  reportante: { nombre_completo: string; fecha: string }
  evidencia: { nombre_archivo: string; tamano_bytes: number | null; disponible: boolean } | null
  /** La versión más nueva primero. */
  historial: RecordVersion[]
}

/** Cuerpo de la rectificación: solo el tipo de falta y la descripción se pueden corregir. */
export interface RectificationInput {
  id_tipo_falta: number
  descripcion: string | null
  motivo: string
  /** Número de la versión vigente que la persona vio al abrir el formulario. */
  version_base: number
}
