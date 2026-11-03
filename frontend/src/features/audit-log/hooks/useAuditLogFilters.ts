import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { AuditLogFilters } from '../types/auditLog.types'
import {
  NO_FILTERS,
  hasActiveFilters,
  isCanonical,
  parseFilters,
  rangeError as rangeErrorOf,
  withFilters,
} from '../utils/filters'

export const SEARCH_DEBOUNCE_MS = 300

/**
 * Filtros del listado, guardados en la URL (`?q=&accion=&modulo=&desde=&hasta=&page=`) para que sobrevivan
 * a la recarga.
 *
 * El usuario se escribe en un campo local y pasa a la URL tras una pausa; un valor inválido en la URL se
 * reemplaza (sin sumar una entrada al historial) por el valor por defecto. Cambiar cualquier filtro vuelve
 * a la primera página. Los demás parámetros de la URL (`detalle`) no se tocan.
 */
export function useAuditLogFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => parseFilters(searchParams), [searchParams])

  const [searchInput, setSearchInput] = useState(filters.q)
  // Lo último que este hook escribió en la URL: distingue un cambio externo (limpiar, atrás) del propio.
  const lastWritten = useRef(filters.q)

  const update = useCallback(
    (change: (current: AuditLogFilters) => AuditLogFilters, replace: boolean) => {
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

  const setAction = useCallback(
    (accion: string | null, replace = false) => update((current) => ({ ...current, accion, page: 1 }), replace),
    [update]
  )

  const setModule = useCallback(
    (modulo: string | null, replace = false) => update((current) => ({ ...current, modulo, page: 1 }), replace),
    [update]
  )

  const setSince = useCallback(
    (desde: string | null) => update((current) => ({ ...current, desde, page: 1 }), false),
    [update]
  )

  const setUntil = useCallback(
    (hasta: string | null) => update((current) => ({ ...current, hasta, page: 1 }), false),
    [update]
  )

  const setPage = useCallback(
    (page: number, replace = false) => update((current) => ({ ...current, page }), replace),
    [update]
  )

  const clear = useCallback(() => {
    lastWritten.current = ''
    setSearchInput('')
    update(() => NO_FILTERS, false)
  }, [update])

  return {
    filters,
    searchInput,
    setSearchInput,
    setAction,
    setModule,
    setSince,
    setUntil,
    setPage,
    clear,
    hasActiveFilters: hasActiveFilters(filters),
    rangeError: rangeErrorOf(filters),
  }
}
