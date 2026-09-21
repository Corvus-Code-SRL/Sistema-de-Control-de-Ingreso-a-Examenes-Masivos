import { useCallback, useState } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import type { ApiError } from '@/lib/api-client'
import { DEFAULT_PER_PAGE, getSubjectCatalog } from '../services/subjectsService'
import type { SubjectCareer, SubjectCatalogPage } from '../types/subject.types'

export interface UseSubjectCatalogResult {
  subjects: SubjectCareer[]
  page: SubjectCatalogPage | null
  isLoading: boolean
  /** El catálogo institucional no devolvió ninguna materia. */
  isEmpty: boolean
  error: ApiError | null
  currentPage: number
  goToPage: (page: number) => void
  reload: () => void
}

/**
 * Página actual del catálogo de materias.
 *
 * El número de página vive aquí y viaja al servidor en cada consulta: cambiar
 * de página relanza la petición en lugar de recortar una lista ya descargada.
 */
export function useSubjectCatalog(perPage: number = DEFAULT_PER_PAGE): UseSubjectCatalogResult {
  const [currentPage, setCurrentPage] = useState(1)

  const resource = useAsyncResource<SubjectCatalogPage>(
    useCallback(
      (signal) => getSubjectCatalog({ page: currentPage, perPage }, signal),
      [currentPage, perPage]
    ),
    [currentPage, perPage]
  )

  const goToPage = useCallback((page: number) => setCurrentPage(Math.max(1, page)), [])

  return {
    subjects: resource.data?.items ?? [],
    page: resource.data,
    isLoading: resource.status === 'loading',
    isEmpty: resource.status === 'success' && resource.data?.total === 0,
    error: resource.error,
    currentPage,
    goToPage,
    reload: resource.reload,
  }
}
