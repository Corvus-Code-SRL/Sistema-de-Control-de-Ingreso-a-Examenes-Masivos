import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAuditLog } from '../services/auditLogService'
import type { AuditLogFilters, AuditLogPage } from '../types/auditLog.types'

interface Result {
  key: string
  page: AuditLogPage
}

const NO_ROWS: AuditLogPage = { items: [], page: 1, perPage: 50, total: 0, totalPages: 1 }

/**
 * Operaciones del listado para unos filtros.
 *
 * Una respuesta que llega tarde nunca pisa a una más nueva: `useAsyncResource` cancela la petición anterior
 * al cambiar los filtros e ignora lo que ella devuelva. Mientras llega la nueva se conserva la página
 * anterior en pantalla; `isStale` avisa que esos datos ya no son los de los filtros actuales.
 *
 * Con `enabled` en falso (rango de fechas inválido) no se pide nada al servidor.
 */
export function useAuditLog(filters: AuditLogFilters, enabled: boolean) {
  const { q, accion, modulo, desde, hasta, page } = filters
  const key = `${enabled}|${q}|${accion ?? ''}|${modulo ?? ''}|${desde ?? ''}|${hasta ?? ''}|${page}`

  const resource = useAsyncResource<Result>(
    useCallback(
      async (signal) => ({
        key,
        page: enabled ? await getAuditLog({ q, accion, modulo, desde, hasta, page }, signal) : NO_ROWS,
      }),
      // La clave resume todos los filtros.
      // eslint-disable-next-line react-hooks/exhaustive-deps
      [key]
    ),
    [key]
  )

  return {
    page: resource.data?.page ?? null,
    isStale: resource.data !== null && resource.data.key !== key,
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
