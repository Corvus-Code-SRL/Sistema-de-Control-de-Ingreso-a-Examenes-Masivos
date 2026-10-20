import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { GroupStudentFilters } from '../types/group.types'
import {
  isCanonicalStudentFilters,
  parseStudentFilters,
  withStudentFilters,
} from '../utils/studentFilters'

export const SEARCH_DEBOUNCE_MS = 300

/**
 * Búsqueda y página de la lista de estudiantes, guardadas en la URL (`?q=`, `?page=`) para que
 * sobrevivan a la recarga y se puedan compartir.
 *
 * La búsqueda se escribe en un campo local y pasa a la URL tras una pausa; un valor inválido en la
 * URL se reemplaza (sin sumar una entrada al historial) por el valor por defecto. Cambiar la
 * búsqueda vuelve a la primera página.
 */
export function useGroupStudentsFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => parseStudentFilters(searchParams), [searchParams])

  const [searchInput, setSearchInput] = useState(filters.q)
  // Lo último que este hook escribió en la URL: distingue un cambio externo (limpiar, atrás) del propio.
  const lastWritten = useRef(filters.q)

  const update = useCallback(
    (change: (current: GroupStudentFilters) => GroupStudentFilters, replace: boolean) => {
      setSearchParams(
        (current) => withStudentFilters(current, change(parseStudentFilters(current))),
        { replace }
      )
    },
    [setSearchParams]
  )

  useEffect(() => {
    if (!isCanonicalStudentFilters(searchParams)) {
      update((current) => current, true)
    }
  }, [searchParams, update])

  useEffect(() => {
    const term = searchInput.trim()

    if (term === filters.q) return

    const timer = window.setTimeout(() => {
      lastWritten.current = term
      update(() => ({ q: term, page: 1 }), true)
    }, SEARCH_DEBOUNCE_MS)

    return () => window.clearTimeout(timer)
  }, [searchInput, filters.q, update])

  useEffect(() => {
    if (filters.q === lastWritten.current) return

    lastWritten.current = filters.q
    setSearchInput(filters.q)
  }, [filters.q])

  const setPage = useCallback(
    (page: number, replace = false) => update((current) => ({ ...current, page }), replace),
    [update]
  )

  const clear = useCallback(() => {
    lastWritten.current = ''
    setSearchInput('')
    update(() => ({ q: '', page: 1 }), false)
  }, [update])

  return {
    filters,
    searchInput,
    setSearchInput,
    setPage,
    clear,
  }
}
