import { apiClient } from '@/lib/api-client'
import type {
  AuditLogDetail,
  AuditLogFilters,
  AuditLogListResponse,
  AuditLogOptions,
  AuditLogPage,
} from '../types/auditLog.types'

/**
 * Operaciones de la bitácora, de la más reciente a la más antigua, 50 por página.
 *
 * La búsqueda sin tildes, el rango en hora de La Paz y el módulo los resuelve el servidor; la vista solo
 * manda lo que el usuario eligió.
 */
export async function getAuditLog(
  { q, accion, modulo, desde, hasta, page }: AuditLogFilters,
  signal?: AbortSignal
): Promise<AuditLogPage> {
  const response = await apiClient<AuditLogListResponse>('/bitacora', {
    query: {
      q: q === '' ? undefined : q,
      accion: accion ?? undefined,
      modulo: modulo ?? undefined,
      desde: desde ?? undefined,
      hasta: hasta ?? undefined,
      page,
    },
    signal,
  })

  return {
    items: response.data,
    page: response.meta.current_page,
    perPage: response.meta.per_page,
    total: response.meta.total,
    totalPages: Math.max(1, response.meta.last_page),
  }
}

export async function getAuditLogEntry(id: number, signal?: AbortSignal): Promise<AuditLogDetail> {
  const response = await apiClient<{ data: AuditLogDetail }>(`/bitacora/${id}`, { signal })

  return response.data
}

/** Valores de los selectores de acción y módulo; una respuesta incompleta deja los selectores vacíos. */
export async function getAuditLogOptions(signal?: AbortSignal): Promise<AuditLogOptions> {
  const response = await apiClient<{ data?: Partial<AuditLogOptions> }>('/bitacora/opciones', { signal })

  return {
    acciones: response.data?.acciones ?? [],
    modulos: response.data?.modulos ?? [],
  }
}
