import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import type { ApiError } from '@/lib/api-client'
import { getMyCourses } from '../services/myCoursesService'
import type { Course } from '../types/group.types'

export interface UseMyCoursesResult {
  courses: Course[]
  isLoading: boolean
  /** El docente no dicta ningún grupo en el periodo activo. */
  isEmpty: boolean
  error: ApiError | null
  reload: () => void
}

/** Todos los cursos del docente, de cualquier carrera o facultad. */
export function useMyCourses(): UseMyCoursesResult {
  const resource = useAsyncResource<Course[]>(
    useCallback((signal) => getMyCourses(signal), []),
    []
  )

  return {
    courses: resource.data ?? [],
    isLoading: resource.status === 'loading',
    isEmpty: resource.status === 'success' && (resource.data?.length ?? 0) === 0,
    error: resource.error,
    reload: resource.reload,
  }
}
