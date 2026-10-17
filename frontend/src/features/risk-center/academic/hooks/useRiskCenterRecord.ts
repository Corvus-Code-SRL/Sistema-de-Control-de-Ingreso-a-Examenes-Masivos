import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getRiskCenterRecord } from '../services/riskCenterService'
import type { RiskCenterRecordDetail } from '../types/riskCenter.types'

/**
 * Detalle de un registro. `record` es null mientras el abierto no sea el pedido: el hook conserva el
 * anterior mientras llega el nuevo, y mostrarlo sería enseñar el registro equivocado.
 */
export function useRiskCenterRecord(id: number) {
  const resource = useAsyncResource<RiskCenterRecordDetail>(
    useCallback((signal) => getRiskCenterRecord(id, signal), [id]),
    [id]
  )

  const isCurrent = resource.data?.id_cr_estudiante === id

  return {
    record: isCurrent ? resource.data : null,
    // Un error de la petición anterior no es el de este registro mientras no haya respuesta nueva.
    status: resource.status === 'success' && !isCurrent ? ('loading' as const) : resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
