import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getRectifiableRecord } from '../services/riskRectificationService'
import type { RectifiableRecordDetail } from '../types/riskRectification.types'

/**
 * Ficha de un registro activo. `record` es null mientras lo cargado no sea el pedido: el hook conserva la
 * respuesta anterior mientras llega la nueva, y mostrarla sería enseñar otro registro.
 */
export function useRectifiableRecord(recordId: number) {
  const resource = useAsyncResource<RectifiableRecordDetail>(
    useCallback((signal) => getRectifiableRecord(recordId, signal), [recordId]),
    [recordId]
  )

  const isCurrent = resource.data?.id_cr_estudiante === recordId

  return {
    record: isCurrent ? resource.data : null,
    status: resource.status === 'success' && !isCurrent ? ('loading' as const) : resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
