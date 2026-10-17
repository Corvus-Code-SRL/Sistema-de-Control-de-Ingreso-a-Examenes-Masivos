import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getFaultTypes } from '../services/riskCenterService'
import type { FaultType } from '../types/riskCenter.types'

/** Catálogo de tipos de falta para el filtro del listado. */
export function useFaultTypes() {
  const resource = useAsyncResource<FaultType[]>(
    useCallback((signal) => getFaultTypes(signal), []),
    []
  )

  return {
    faultTypes: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
