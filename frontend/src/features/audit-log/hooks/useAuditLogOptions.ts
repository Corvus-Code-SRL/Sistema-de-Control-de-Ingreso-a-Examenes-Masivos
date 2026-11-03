import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAuditLogOptions } from '../services/auditLogService'
import type { AuditLogOptions } from '../types/auditLog.types'

const NO_OPTIONS: AuditLogOptions = { acciones: [], modulos: [] }

/** Acciones y módulos de los selectores. Si no cargan, el listado sigue funcionando sin esos filtros. */
export function useAuditLogOptions() {
  const resource = useAsyncResource<AuditLogOptions>(
    useCallback((signal) => getAuditLogOptions(signal), []),
    []
  )

  return { options: resource.data ?? NO_OPTIONS, status: resource.status }
}
