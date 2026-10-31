import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  isCanonicalAccountUrl,
  parseAccountFilters,
  withAccountFilters,
} from '../lib/accountFilters'
import type { AccountFilters } from '../types/users.types'

export const SEARCH_DEBOUNCE_MS = 300

/**
 * Filtros del listado de cuentas, guardados en la URL (`?q=`, `?rol=`, `?page=`) para que sobrevivan
 * a la recarga y al volver desde el detalle.
 *
 * La búsqueda se escribe en un campo local y pasa a la URL tras una pausa; un valor inválido en la
 * URL se reemplaza (sin sumar una entrada al historial) por el valor por defecto. Cambiar la
 * búsqueda o el rol vuelve a la primera página.
 */
export function useAccountFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => parseAccountFilters(searchParams), [searchParams])

  const [searchInput, setSearchInput] = useState(filters.q)
  // Lo último que este hook escribió en la URL: distingue un cambio externo (limpiar, atrás) del propio.
  const lastWritten = useRef(filters.q)

  const update = useCallback(
    (change: (current: AccountFilters) => AccountFilters, replace: boolean) => {
      setSearchParams((current) => withAccountFilters(current, change(parseAccountFilters(current))), {
        replace,
      })
    },
    [setSearchParams]
  )

  useEffect(() => {
    if (!isCanonicalAccountUrl(searchParams)) {
      update((current) => current, true)
    }
  }, [searchParams, update])

  useEffect(() => {
    const term = searchInput.trim()

    if (term === filters.q) return

    const timer = window.setTimeout(() => {
      lastWritten.current = term
      update((current) => ({ ...current, q: term, page: 1 }), true)
    }, SEARCH_DEBOUNCE_MS)

    return () => window.clearTimeout(timer)
  }, [searchInput, filters.q, update])

  useEffect(() => {
    if (filters.q === lastWritten.current) return

    lastWritten.current = filters.q
    setSearchInput(filters.q)
  }, [filters.q])

  const setRole = useCallback(
    (rol: string | null, replace = false) => update((current) => ({ ...current, rol, page: 1 }), replace),
    [update]
  )

  const setPage = useCallback(
    (page: number, replace = false) => update((current) => ({ ...current, page }), replace),
    [update]
  )

  const clear = useCallback(() => {
    lastWritten.current = ''
    setSearchInput('')
    update(() => ({ q: '', rol: null, page: 1 }), false)
  }, [update])

  return {
    filters,
    searchInput,
    setSearchInput,
    setRole,
    setPage,
    clear,
    hasActiveFilters: filters.q !== '' || filters.rol !== null,
  }
}
