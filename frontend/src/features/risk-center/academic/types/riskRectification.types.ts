import type { FaultType } from './riskCenter.types'

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

/** Un registro activo de la Central: el que se puede rectificar. */
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
  tipo_falta: FaultType
  aprobador: { nombre_completo: string; fecha: string } | null
}

/** Un registro ya retirado de la Central: solo se consulta. */
export interface RectifiedRecord extends RectifiableRecord {
  rectificacion: {
    /** ISO 8601. */
    fecha: string
    administrador: { nombre_completo: string | null }
    motivo: string
  }
}

export interface StudentRecords {
  estudiante: RectifiableStudent
  /** Registros activos, del más reciente al más antiguo. */
  registros: RectifiableRecord[]
  /** Registros ya rectificados, el último rectificado primero. */
  rectificados: RectifiedRecord[]
}

/** La ficha completa de un registro activo, de solo lectura. */
export interface RectifiableRecordDetail {
  id_cr_estudiante: number
  estudiante: RectifiableStudent
  examen: RectifiableRecord['examen'] & { carrera: string | null }
  /** Cuándo ocurrió el hecho: el intento de ingreso si lo hubo y, si no, el reporte. */
  hecho: { fecha_hora: string }
  ambiente: { nro_aula: string | null } | null
  tipo_falta: FaultType
  descripcion: string | null
  evidencia: { nombre_archivo: string; tamano_bytes: number | null; disponible: boolean } | null
  reportante: { nombre_completo: string; fecha: string }
  aprobador: { nombre_completo: string; fecha: string } | null
  fecha_registro: string
}

/** Cuerpo de la rectificación: solo el motivo. */
export interface RectificationInput {
  motivo: string
}
