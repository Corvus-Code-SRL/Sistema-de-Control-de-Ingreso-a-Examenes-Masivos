import { useCallback, useState } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getRectifiableRecord } from '../services/riskRectificationService'
import type { RectifiableRecordDetail } from '../types/riskRectification.types'

/**
 * Detalle de un registro con su historial. Tras rectificar, `replace` pone el registro que devolvió el
 * servidor sin pedirlo otra vez. `record` es null mientras lo cargado no sea el pedido.
 */
export function useRectifiableRecord(recordId: number) {
  const resource = useAsyncResource<RectifiableRecordDetail>(
    useCallback((signal) => getRectifiableRecord(recordId, signal), [recordId]),
    [recordId]
  )
  const [replaced, setReplaced] = useState<RectifiableRecordDetail | null>(null)
  const { reload: reloadResource } = resource

  const reload = useCallback(() => {
    setReplaced(null)
    reloadResource()
  }, [reloadResource])

  const loaded = replaced?.id_cr_estudiante === recordId ? replaced : resource.data
  const isCurrent = loaded?.id_cr_estudiante === recordId

  return {
    record: isCurrent ? loaded : null,
    status: resource.status === 'success' && !isCurrent ? ('loading' as const) : resource.status,
    error: resource.error,
    reload,
    replace: setReplaced,
  }
}
