import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getAcademicPeriods } from '../services/subjectsService'
import type { AcademicPeriod } from '../types/subject.types'

/**
 * Períodos académicos para el selector de Asignaciones, del más reciente al más antiguo.
 *
 * TODO(Sprint 4): duplica features/groups/hooks/usePeriods; pasar a un módulo compartido.
 */
export function useAcademicPeriods() {
  const resource = useAsyncResource<AcademicPeriod[]>(
    useCallback((signal) => getAcademicPeriods(signal), []),
    []
  )

  return {
    periods: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload,
  }
}
