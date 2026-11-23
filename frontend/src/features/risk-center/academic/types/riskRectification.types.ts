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

/** Archivo adjunto descrito por el servidor (evidencia del reporte o imagen de un rechazo). */
export interface EvidenceInfo {
  nombre_archivo: string
  tamano_bytes: number | null
  disponible: boolean
}

/** De dónde sale la fecha desde la que un docente inactivo cuenta como inactivo. */
export type InactiveSource = 'ROL_CERRADO' | 'PRIMERA_DETECCION'

/** El docente que aprobó el reporte y si todavía puede responder. */
export interface ApprovingTeacher {
  nombre_completo: string | null
  activo: boolean
  inactivo_desde: string | null
  fuente: InactiveSource | null
  /** Desde cuándo el Administrador puede anular sin su confirmación; null si no se conoce. */
  puede_rectificar_desde: string | null
  puede_rectificar_sin_confirmacion: boolean
}

/** Resumen de las solicitudes de rectificación de un registro activo. */
export interface RectificationSummary {
  solicitud_pendiente: {
    id_solicitud_rect: number
    motivo: string
    fecha_solicitud: string
    vence_en: string
    docente: { nombre_completo: string | null }
  } | null
  rechazos: number
  vencidas: number
  ultimo_rechazo: {
    id_solicitud_rect: number
    motivo: string
    fecha: string
    evidencia: EvidenceInfo | null
  } | null
  docente: ApprovingTeacher
  dias_vencimiento: number
  dias_docente_inactivo: number
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
  /** Solo en los registros activos; los ya rectificados traen su propia `rectificacion`. */
  rectificacion?: RectificationSummary
}

/** Un registro ya retirado de la Central: solo se consulta. */
export interface RectifiedRecord extends Omit<RectifiableRecord, 'rectificacion'> {
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
  evidencia: EvidenceInfo | null
  reportante: { nombre_completo: string; fecha: string }
  aprobador: { nombre_completo: string; fecha: string } | null
  fecha_registro: string
  rectificacion: RectificationSummary
}

/** Cuerpo de la solicitud o de la anulación directa: solo el motivo. */
export interface RectificationInput {
  motivo: string
}

export type RectificationRequestState = 'PENDIENTE' | 'ACEPTADA' | 'RECHAZADA' | 'VENCIDA'

/** La solicitud recién enviada al docente. */
export interface SentRectificationRequest {
  id_solicitud_rect: number
  id_cr_estudiante: number
  estado: RectificationRequestState
  motivo: string
  docente: { nombre_completo: string | null }
  fecha_solicitud: string
  vence_en: string
}

/** Una solicitud en la bandeja del docente. `estado` es el efectivo: una pendiente pasada de fecha llega VENCIDA. */
export interface RectificationRequestCard {
  id_solicitud_rect: number
  id_cr_estudiante: number
  estado: RectificationRequestState
  fecha_solicitud: string
  vence_en: string
  fecha_respuesta: string | null
  motivo_solicitud: string
  administrador: { nombre_completo: string }
  estudiante: { id_estudiante: number; nombre_completo: string; cod_sis: string }
  examen: { id_examen: number; nombre: string; materia: string | null; fecha: string }
  hecho: { fecha_hora: string }
  tipo_falta: FaultType
  descripcion: string | null
  fecha_registro: string
  evidencia: EvidenceInfo | null
  rechazos_previos: number
  registro_rectificado: boolean
  respuesta: { motivo: string; evidencia: EvidenceInfo | null } | null
}

export interface RectificationInbox {
  solicitudes: RectificationRequestCard[]
  totalPendientes: number
}
