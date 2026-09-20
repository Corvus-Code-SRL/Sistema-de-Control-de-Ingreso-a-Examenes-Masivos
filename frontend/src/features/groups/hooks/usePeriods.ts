import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getPeriods, type Periods } from '../services/periodsService'

export interface UsePeriodsResult {
  periods: Periods['periods']
  activePeriodId: number | null
  isLoading: boolean
  error: ReturnType<typeof useAsyncResource<Periods>>['error']
}

export function usePeriods(): UsePeriodsResult {
  const resource = useAsyncResource<Periods>(
    useCallback((signal) => getPeriods(signal), []),
    []
  )

  return {
    periods: resource.data?.periods ?? [],
    activePeriodId: resource.data?.activePeriodId ?? null,
    isLoading: resource.status === 'loading',
    error: resource.error,
  }
}