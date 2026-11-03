import type { Page } from '@/types/api.types'

/** Quién figura como autor: una persona, una cuenta de sistema o el proceso automático de un examen. */
export type AuditActorKind = 'persona' | 'sistema' | 'automatico'

export interface AuditActor {
  id_usuario: string
  nombre_completo: string | null
  cod_sis: string | null
  tipo: AuditActorKind
  /** Lo que se muestra: el nombre, «Sistema» o «Automático». */
  etiqueta: string
}

export interface AuditAction {
  /** Null cuando la fila no tiene acción asociada. */
  codigo: string | null
  etiqueta: string
}

export interface AuditModule {
  clave: string
  etiqueta: string
}

export interface AuditRecordSummary {
  tabla: string
  etiqueta: string
  /** «Grupo #7», «Rol de cuenta: Nombre (SIS)». */
  descripcion: string
}

/** Una fila del listado. Los valores crudos de la operación no viajan. */
export interface AuditLogEntry {
  id_log: number
  /** ISO 8601. */
  fecha_hora: string
  usuario: AuditActor
  accion: AuditAction
  modulo: AuditModule
  registro: AuditRecordSummary
  /** Inicio o cierre automático de un examen. */
  automatico: boolean
}

export type AuditValue = string | number | boolean | null | AuditValue[] | { [key: string]: AuditValue }

/** Un campo de la operación. Si `oculto`, no trae valores: nunca llegan al navegador. */
export interface AuditChange {
  campo: string
  etiqueta: string
  oculto: boolean
  anterior?: AuditValue
  nuevo?: AuditValue
}

export interface AuditLogDetail extends Omit<AuditLogEntry, 'registro'> {
  registro: AuditRecordSummary & { ids: Record<string, AuditValue> }
  /** Solo en las operaciones automáticas: el docente dueño del examen. */
  docente_examen: { nombre_completo: string; cod_sis: string } | null
  cambios: AuditChange[]
}

export interface AuditLogOptions {
  acciones: { codigo: string; etiqueta: string }[]
  modulos: AuditModule[]
}

export interface AuditLogListResponse {
  data: AuditLogEntry[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type AuditLogPage = Page<AuditLogEntry>

/** Filtros del listado; viven en la URL (`?q=&accion=&modulo=&desde=&hasta=&page=`). */
export interface AuditLogFilters {
  q: string
  accion: string | null
  modulo: string | null
  /** YYYY-MM-DD, fecha de La Paz. */
  desde: string | null
  hasta: string | null
  page: number
}
