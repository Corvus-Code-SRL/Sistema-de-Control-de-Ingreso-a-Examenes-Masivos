import type { AuditLogFilters } from '../types/auditLog.types'

const MAX_PAGE = 100000
export const MAX_SEARCH_LENGTH = 100
export const RANGE_ERROR = 'La fecha de inicio debe ser anterior a la fecha de fin.'

/** Parámetros de la URL que son filtros; el resto (p. ej. `detalle`) no se toca. */
export const FILTER_KEYS = ['q', 'accion', 'modulo', 'desde', 'hasta', 'page'] as const

export const NO_FILTERS: AuditLogFilters = {
  q: '',
  accion: null,
  modulo: null,
  desde: null,
  hasta: null,
  page: 1,
}

/** Una fecha YYYY-MM-DD que existe en el calendario («2026-02-31» no); si no, null. */
export function validDate(value: string | null): string | null {
  if (value === null || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null

  const date = new Date(`${value}T00:00:00Z`)

  return !Number.isNaN(date.getTime()) && date.toISOString().startsWith(value) ? value : null
}

function boundedPage(value: string | null): number {
  if (value === null || !/^\d{1,6}$/.test(value)) return 1

  const page = Number(value)

  return page >= 1 && page <= MAX_PAGE ? page : 1
}

/** Lee los filtros de la URL; lo que no sea válido vuelve al valor por defecto, sin avisar. */
export function parseFilters(params: URLSearchParams): AuditLogFilters {
  const q = (params.get('q') ?? '').trim()
  const accion = params.get('accion')
  const modulo = params.get('modulo')

  return {
    q: q.length <= MAX_SEARCH_LENGTH ? q : '',
    accion: accion !== null && /^[A-Z0-9_]{1,100}$/.test(accion) ? accion : null,
    modulo: modulo !== null && /^[a-z_]{1,30}$/.test(modulo) ? modulo : null,
    desde: validDate(params.get('desde')),
    hasta: validDate(params.get('hasta')),
    page: boundedPage(params.get('page')),
  }
}

/** Aplica los filtros a una URL conservando el resto de sus parámetros; los valores por defecto no se escriben. */
export function withFilters(current: URLSearchParams, filters: AuditLogFilters): URLSearchParams {
  const next = new URLSearchParams(current)

  FILTER_KEYS.forEach((key) => next.delete(key))

  if (filters.q !== '') next.set('q', filters.q)
  if (filters.accion !== null) next.set('accion', filters.accion)
  if (filters.modulo !== null) next.set('modulo', filters.modulo)
  if (filters.desde !== null) next.set('desde', filters.desde)
  if (filters.hasta !== null) next.set('hasta', filters.hasta)
  if (filters.page > 1) next.set('page', String(filters.page))

  return next
}

/** ¿La URL ya está escrita en su forma canónica? Si no, hay que reemplazarla. */
export function isCanonical(params: URLSearchParams): boolean {
  const canonical = withFilters(params, parseFilters(params))

  return FILTER_KEYS.every((key) => params.get(key) === canonical.get(key))
}

/** ¿Hay algún filtro puesto (la página no cuenta)? */
export function hasActiveFilters(filters: AuditLogFilters): boolean {
  return (
    filters.q !== '' ||
    filters.accion !== null ||
    filters.modulo !== null ||
    filters.desde !== null ||
    filters.hasta !== null
  )
}

/** El rango es válido si falta alguno de los dos extremos o «Desde» no pasa de «Hasta». */
export function rangeError(filters: Pick<AuditLogFilters, 'desde' | 'hasta'>): string | null {
  return filters.desde !== null && filters.hasta !== null && filters.desde > filters.hasta ? RANGE_ERROR : null
}

/** Id del detalle abierto (`?detalle=`): entero positivo de hasta 9 dígitos; null si no hay o no es válido. */
export function detailIdFrom(params: URLSearchParams): { present: boolean; id: number | null } {
  const raw = params.get('detalle')

  if (raw === null) return { present: false, id: null }

  return { present: true, id: /^[1-9]\d{0,8}$/.test(raw) ? Number(raw) : null }
}
