import { apiClient } from '@/lib/api-client'

import type {
  AuditLogReportExportFilters,
  AuditLogReportFilters,
  AuditLogReportPage,
  AuditLogReportResponse
} from '../types/auditLogReport.types'

/** Build the shared query for report and export requests. */
function reportQuery({ desde, hasta, q, accion, modulo }: AuditLogReportExportFilters) {
  return {
    desde,
    hasta,
    q: q.trim() || undefined,
    accion: accion ?? undefined,
    modulo: modulo ?? undefined
  }
}

/** HU-068: get the paginated report and global totals. */
export async function getAuditLogReport(
  filters: AuditLogReportFilters,
  signal?: AbortSignal
): Promise<AuditLogReportPage> {
  const response = await apiClient<AuditLogReportResponse>('/reportes/bitacora', {
    query: {
      ...reportQuery(filters),
      page: filters.page
    },
    signal
  })

  return {
    items: response.data,
    totals: response.totales,
    filters: response.filtros,
    canExport: response.puede_exportar,
    page: response.meta.current_page,
    perPage: response.meta.per_page,
    total: response.meta.total,
    totalPages: Math.max(1, response.meta.last_page)
  }
}

/** Download all filtered rows as PDF. */
export function getAuditLogReportPdf(
  filters: AuditLogReportExportFilters,
  signal?: AbortSignal
): Promise<Blob> {
  return apiClient<Blob>('/reportes/bitacora/pdf', {
    query: reportQuery(filters),
    responseType: 'blob',
    signal
  })
}

/** Download all filtered rows as Excel. */
export function getAuditLogReportExcel(
  filters: AuditLogReportExportFilters,
  signal?: AbortSignal
): Promise<Blob> {
  return apiClient<Blob>('/reportes/bitacora/excel', {
    query: reportQuery(filters),
    responseType: 'blob',
    signal
  })
}
