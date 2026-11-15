import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getRectificationFaultTypes } from '../services/riskRectificationService'
import type { RectificationFaultType } from '../types/riskRectification.types'

/** Catálogo de tipos de falta del selector del formulario de rectificación. */
export function useRectificationFaultTypes() {
  const resource = useAsyncResource<RectificationFaultType[]>(
    useCallback((signal) => getRectificationFaultTypes(signal), []),
    []
  )

  return {
    faultTypes: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
