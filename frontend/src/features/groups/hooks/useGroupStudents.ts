import { useCallback } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { getGroupStudents } from '../services/groupsService'
import type { GroupStudentFilters, GroupStudentsPage } from '../types/group.types'

interface Result {
  key: string
  page: GroupStudentsPage
}

/**
 * Estudiantes del grupo para una búsqueda y una página.
 *
 * Una respuesta que llega tarde nunca pisa a una más nueva: `useAsyncResource` cancela la petición
 * anterior al cambiar la búsqueda e ignora lo que ella devuelva. Mientras llega la nueva se conserva
 * la página anterior en pantalla; `isStale` avisa que esos datos ya no son los de la URL actual.
 */
export function useGroupStudents(groupId: number, { q, page }: GroupStudentFilters) {
  const key = `${groupId}|${q}|${page}`

  const resource = useAsyncResource<Result>(
    useCallback(
      async (signal) => ({ key, page: await getGroupStudents(groupId, { q, page }, signal) }),
      // La clave resume el grupo, la búsqueda y la página.
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
