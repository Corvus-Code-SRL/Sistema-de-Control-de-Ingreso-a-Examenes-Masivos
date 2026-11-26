import { useCallback, useEffect, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

import type {
  AuditLogReportFilters,
  AuditLogReportExportFilters
} from '../types/auditLogReport.types'

export const DEFAULT_RANGE_DAYS = 30

const MAX_PAGE = 100000
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/
const ACTION_CODE = /^[A-Z0-9_]+$/

const MODULES = new Set([
  'cuentas',
  'roles',
  'grupos',
  'catalogo',
  'ambientes',
  'examenes',
  'auxiliares',
  'ingreso',
  'incidencias',
  'situaciones',
  'central_riesgo',
  'otros'
])

/** Calendar date in Bolivia. */
export function todayIso(): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/La_Paz',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).formatToParts(new Date())

  const year = parts.find((part) => part.type === 'year')?.value
  const month = parts.find((part) => part.type === 'month')?.value
  const day = parts.find((part) => part.type === 'day')?.value

  return `${year}-${month}-${day}`
}

export function isoDaysAgo(days: number): string {
  const date = new Date(`${todayIso()}T00:00:00Z`)

  date.setUTCDate(date.getUTCDate() - days)

  return date.toISOString().slice(0, 10)
}

export function isValidDate(value: string | null): value is string {
  if (value === null || !ISO_DATE.test(value)) {
    return false
  }

  const date = new Date(`${value}T00:00:00Z`)

  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
}

export function rangeError(desde: string, hasta: string): string | null {
  if (!desde) {
    return 'La fecha de inicio es obligatoria.'
  }

  if (!hasta) {
    return 'La fecha de fin es obligatoria.'
  }

  if (!isValidDate(desde) || !isValidDate(hasta)) {
    return 'Ingrese fechas validas.'
  }

  if (desde > hasta) {
    return 'La fecha de inicio debe ser anterior a la fecha de fin.'
  }

  return null
}

export function defaultReportFilters(): AuditLogReportFilters {
  return {
    desde: isoDaysAgo(DEFAULT_RANGE_DAYS),
    hasta: todayIso(),
    q: '',
    accion: null,
    modulo: null,
    page: 1
  }
}

function parseFilters(
  params: URLSearchParams,
  defaults: AuditLogReportFilters
): AuditLogReportFilters {
  const desde = params.get('desde')
  const hasta = params.get('hasta')
  const q = params.get('q') ?? ''
  const accion = params.get('accion')
  const modulo = params.get('modulo')
  const pageRaw = params.get('page')

  const validDesde = isValidDate(desde) ? desde : defaults.desde

  const validHasta = isValidDate(hasta) ? hasta : defaults.hasta

  const validRange = rangeError(validDesde, validHasta) === null

  const page =
    pageRaw !== null && /^[1-9]\d{0,5}$/.test(pageRaw) && Number(pageRaw) <= MAX_PAGE
      ? Number(pageRaw)
      : 1

  return {
    desde: validRange ? validDesde : defaults.desde,
    hasta: validRange ? validHasta : defaults.hasta,
    q: q.trim().slice(0, 100),
    accion: accion !== null && accion.length <= 100 && ACTION_CODE.test(accion) ? accion : null,
    modulo: modulo !== null && MODULES.has(modulo) ? modulo : null,
    page
  }
}

function withFilters(current: URLSearchParams, filters: AuditLogReportFilters): URLSearchParams {
  const result = new URLSearchParams(current)

  result.set('desde', filters.desde)
  result.set('hasta', filters.hasta)

  if (filters.q) {
    result.set('q', filters.q)
  } else {
    result.delete('q')
  }

  if (filters.accion !== null) {
    result.set('accion', filters.accion)
  } else {
    result.delete('accion')
  }

  if (filters.modulo !== null) {
    result.set('modulo', filters.modulo)
  } else {
    result.delete('modulo')
  }

  if (filters.page > 1) {
    result.set('page', String(filters.page))
  } else {
    result.delete('page')
  }

  return result
}

/** Applied report filters, persisted in the URL. */
export function useAuditLogReportFilters() {
  const [searchParams, setSearchParams] = useSearchParams()

  const defaults = useMemo(() => defaultReportFilters(), [])

  const filters = useMemo(() => parseFilters(searchParams, defaults), [searchParams, defaults])

  useEffect(() => {
    const canonical = withFilters(searchParams, filters)

    if (canonical.toString() !== searchParams.toString()) {
      setSearchParams(canonical, { replace: true })
    }
  }, [searchParams, filters, setSearchParams])

  const applyFilters = useCallback(
    (next: AuditLogReportExportFilters): boolean => {
      if (rangeError(next.desde, next.hasta) !== null) {
        return false
      }

      setSearchParams(
        (current) =>
          withFilters(current, {
            ...next,
            q: next.q.trim().slice(0, 100),
            page: 1
          }),
        { replace: false }
      )

      return true
    },
    [setSearchParams]
  )

  const setPage = useCallback(
    (page: number, replace = false) => {
      if (!Number.isInteger(page) || page < 1 || page > MAX_PAGE) {
        return
      }

      setSearchParams(
        (current) =>
          withFilters(current, {
            ...parseFilters(current, defaults),
            page
          }),
        { replace }
      )
    },
    [setSearchParams, defaults]
  )

  const clear = useCallback(() => {
    setSearchParams((current) => withFilters(current, defaultReportFilters()), { replace: false })
  }, [setSearchParams])

  return {
    filters,
    applyFilters,
    setPage,
    clear
  }
}
