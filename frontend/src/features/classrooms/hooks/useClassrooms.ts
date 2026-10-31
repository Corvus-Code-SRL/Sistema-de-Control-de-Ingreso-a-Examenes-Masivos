import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getClassrooms } from '../services/classroomsService'
import type { Classroom } from '../types/classroom.types'

export function useClassrooms() {
  const resource = useAsyncResource<Classroom[]>(
    useCallback((signal) => getClassrooms(signal), []),
    []
  )

  return {
    classrooms: resource.data ?? [],
    status: resource.status,
    error: resource.error,
    reload: resource.reload
  }
}
