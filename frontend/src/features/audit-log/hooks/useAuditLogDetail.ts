import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAuditLogEntry } from '../services/auditLogService'
import type { AuditLogDetail } from '../types/auditLog.types'

/**
 * Detalle de una operación. `entry` es null mientras la abierta no sea la pedida: el hook conserva la
 * anterior mientras llega la nueva, y mostrarla sería enseñar la operación equivocada.
 */
export function useAuditLogDetail(id: number) {
  const resource = useAsyncResource<AuditLogDetail>(
    useCallback((signal) => getAuditLogEntry(id, signal), [id]),
    [id]
  )

  const isCurrent = resource.data?.id_log === id

  return {
    entry: isCurrent ? resource.data : null,
    // Un error de la petición anterior no es el de esta operación mientras no haya respuesta nueva.
    status: resource.status === 'success' && !isCurrent ? ('loading' as const) : resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
