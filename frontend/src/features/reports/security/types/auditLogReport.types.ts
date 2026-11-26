import type { AuditLogEntry, AuditLogListResponse } from '@/features/audit-log/types/auditLog.types'

/** HU-068: filtros del reporte de bitacora. */
export interface AuditLogReportFilters {
  desde: string
  hasta: string
  q: string
  accion: string | null
  modulo: string | null
  page: number
}

/** Totales agrupados por accion. */
export interface AuditLogReportActionTotal {
  codigo: string | null
  etiqueta: string
  total: number
}

/** Totales globales, independientes de la paginacion. */
export interface AuditLogReportTotals {
  total_operaciones: number
  total_usuarios: number
  por_accion: AuditLogReportActionTotal[]
}

/** Filtros confirmados por el servidor. */
export interface AuditLogReportAppliedFilters {
  desde: string
  hasta: string
  q: string | null
  accion: string | null
  modulo: string | null
}

/** Respuesta original del endpoint HU-068. */
export interface AuditLogReportResponse extends AuditLogListResponse {
  totales: AuditLogReportTotals
  filtros: AuditLogReportAppliedFilters
  puede_exportar: boolean
}

/** Reporte normalizado para la vista. */
export interface AuditLogReportPage {
  items: AuditLogEntry[]
  totals: AuditLogReportTotals
  filters: AuditLogReportAppliedFilters
  canExport: boolean
  page: number
  perPage: number
  total: number
  totalPages: number
}

export type AuditLogReportExportFilters = Omit<AuditLogReportFilters, 'page'>
