import { useCallback, useEffect, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { AlienAttemptsFilters } from '../types/alienAttempts.types'

export const DEFAULT_RANGE_DAYS = 30

/** YYYY-MM-DD de hoy (en la zona local del navegador). */
export function todayIso(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

/** YYYY-MM-DD de hoy menos `days` días. */
export function isoDaysAgo(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() - days)

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

function isValidDate(value: string | null): value is string {
  return value !== null && ISO_DATE.test(value)
}

function parseFilters(params: URLSearchParams): AlienAttemptsFilters {
  const desde = params.get('desde')
  const hasta = params.get('hasta')
  const facultadRaw = params.get('facultad')
  const pageRaw = params.get('page')

  const defaultDesde = isoDaysAgo(DEFAULT_RANGE_DAYS)
  const defaultHasta = todayIso()

  const validDesde = isValidDate(desde) ? desde : defaultDesde
  const validHasta = isValidDate(hasta) ? hasta : defaultHasta

  const facultad = facultadRaw !== null && /^[1-9]\d{0,9}$/.test(facultadRaw) && Number(facultadRaw) <= 2147483647
    ? Number(facultadRaw)
    : null

  const page = pageRaw !== null && /^[1-9]\d{0,5}$/.test(pageRaw) ? Number(pageRaw) : 1

  return { desde: validDesde, hasta: validHasta, facultad, page }
}

function withFilters(current: URLSearchParams, next: AlienAttemptsFilters, defaults: { desde: string; hasta: string }): URLSearchParams {
  const result = new URLSearchParams(current)

  if (next.desde === defaults.desde) result.delete('desde')
  else result.set('desde', next.desde)

  if (next.hasta === defaults.hasta) result.delete('hasta')
  else result.set('hasta', next.hasta)

  if (next.facultad === null) result.delete('facultad')
  else result.set('facultad', String(next.facultad))

  if (next.page === 1) result.delete('page')
  else result.set('page', String(next.page))

  return result
}

/**
 * Filtros del reporte en la URL (`?desde=&hasta=&facultad=&page=`) para que sobrevivan
 * a la recarga y se puedan compartir. Valores inválidos en la URL se reemplazan por
 * los defaults sin avisar (mismo criterio que la Central de Riesgo).
 *
 * El rango por defecto es los últimos 30 días. Cambiar el rango o la facultad vuelve
 * a la primera página.
 */
export function useAlienAttemptsFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const defaults = useMemo(() => ({ desde: isoDaysAgo(DEFAULT_RANGE_DAYS), hasta: todayIso() }), [])
  const filters = useMemo(() => parseFilters(searchParams), [searchParams])

  // Un valor inválido en la URL se reemplaza por el default sin sumar entrada al historial.
  useEffect(() => {
    const fromUrl = {
      desde: searchParams.get('desde'),
      hasta: searchParams.get('hasta'),
      facultad: searchParams.get('facultad'),
      page: searchParams.get('page'),
    }

    const wanted = withFilters(new URLSearchParams(), filters, defaults)
    const hasInvalid =
      (fromUrl.desde !== null && !isValidDate(fromUrl.desde)) ||
      (fromUrl.hasta !== null && !isValidDate(fromUrl.hasta)) ||
      (fromUrl.facultad !== null && filters.facultad === null) ||
      (fromUrl.page !== null && filters.page === 1)

    if (hasInvalid || wanted.toString() !== searchParams.toString()) {
      setSearchParams(wanted, { replace: true })
    }
  }, [searchParams, filters, defaults, setSearchParams])

  const update = useCallback(
    (next: AlienAttemptsFilters, replace = false) => {
      setSearchParams((current) => withFilters(current, next, defaults), { replace })
    },
    [setSearchParams, defaults]
  )

  const setRange = useCallback(
    (desde: string, hasta: string) => update({ ...filters, desde, hasta, page: 1 }),
    [filters, update]
  )

  const setFaculty = useCallback(
    (facultad: number | null) => update({ ...filters, facultad, page: 1 }),
    [filters, update]
  )

  const setPage = useCallback(
    (page: number, replace = false) => update({ ...filters, page }, replace),
    [filters, update]
  )

  return { filters, setRange, setFaculty, setPage }
}
