import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { RiskCenterFilters } from '../types/riskCenter.types'
import { isCanonical, parseFilters, withFilters } from '../utils/filters'

export const SEARCH_DEBOUNCE_MS = 300

/**
 * Filtros del listado, guardados en la URL (`?q=`, `?tipo=`, `?page=`) para que sobrevivan a la recarga.
 *
 * La búsqueda se escribe en un campo local y pasa a la URL tras una pausa; un valor inválido en la URL
 * se reemplaza (sin sumar una entrada al historial) por el valor por defecto. Cambiar la búsqueda o el
 * tipo vuelve a la primera página.
 */
export function useRiskCenterFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => parseFilters(searchParams), [searchParams])

  const [searchInput, setSearchInput] = useState(filters.q)
  // Lo último que este hook escribió en la URL: distingue un cambio externo (limpiar, atrás) del propio.
  const lastWritten = useRef(filters.q)

  const update = useCallback(
    (change: (current: RiskCenterFilters) => RiskCenterFilters, replace: boolean) => {
      setSearchParams((current) => withFilters(current, change(parseFilters(current))), { replace })
    },
    [setSearchParams]
  )

  useEffect(() => {
    if (!isCanonical(searchParams)) {
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

  const setType = useCallback(
    (tipo: number | null, replace = false) => update((current) => ({ ...current, tipo, page: 1 }), replace),
    [update]
  )

  const setPage = useCallback(
    (page: number, replace = false) => update((current) => ({ ...current, page }), replace),
    [update]
  )

  const clear = useCallback(() => {
    lastWritten.current = ''
    setSearchInput('')
    update(() => ({ q: '', tipo: null, page: 1 }), false)
  }, [update])

  return {
    filters,
    searchInput,
    setSearchInput,
    setType,
    setPage,
    clear,
    hasActiveFilters: filters.q !== '' || filters.tipo !== null,
  }
}
