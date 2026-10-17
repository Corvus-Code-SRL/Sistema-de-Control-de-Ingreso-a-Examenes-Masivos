import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getRiskCenterRecords } from '../services/riskCenterService'
import type { RiskCenterFilters, RiskCenterPage } from '../types/riskCenter.types'

interface Result {
  key: string
  page: RiskCenterPage
}

/**
 * Registros del listado para unos filtros.
 *
 * Una respuesta que llega tarde nunca pisa a una más nueva: `useAsyncResource` cancela la petición
 * anterior al cambiar los filtros e ignora lo que ella devuelva. Mientras llega la nueva se conserva
 * la página anterior en pantalla; `isStale` avisa que esos datos ya no son los de los filtros actuales.
 */
export function useRiskCenterRecords({ q, tipo, page }: RiskCenterFilters) {
  const key = `${q}|${tipo ?? ''}|${page}`

  const resource = useAsyncResource<Result>(
    useCallback(
      async (signal) => ({ key, page: await getRiskCenterRecords({ q, tipo, page }, signal) }),
      // La clave resume los tres filtros.
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
