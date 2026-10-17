import type { RiskCenterFilters } from '../types/riskCenter.types'

const MAX_ID = 2147483647
const MAX_PAGE = 100000
export const MAX_SEARCH_LENGTH = 50

const FILTER_KEYS = ['q', 'tipo', 'page'] as const

/** Entero positivo escrito solo con dígitos y dentro del rango que acepta el servidor; si no, null. */
function boundedInteger(value: string | null, max: number): number | null {
  if (value === null || !/^\d{1,10}$/.test(value)) return null

  const number = Number(value)

  return number >= 1 && number <= max ? number : null
}

/** Lee los filtros de la URL; lo que no sea válido vuelve al valor por defecto, sin avisar. */
export function parseFilters(params: URLSearchParams): RiskCenterFilters {
  const q = (params.get('q') ?? '').trim()

  return {
    q: q.length <= MAX_SEARCH_LENGTH ? q : '',
    tipo: boundedInteger(params.get('tipo'), MAX_ID),
    page: boundedInteger(params.get('page'), MAX_PAGE) ?? 1,
  }
}

/** Aplica los filtros a una URL conservando el resto de sus parámetros; los valores por defecto no se escriben. */
export function withFilters(current: URLSearchParams, filters: RiskCenterFilters): URLSearchParams {
  const next = new URLSearchParams(current)

  FILTER_KEYS.forEach((key) => next.delete(key))

  if (filters.q !== '') next.set('q', filters.q)
  if (filters.tipo !== null) next.set('tipo', String(filters.tipo))
  if (filters.page > 1) next.set('page', String(filters.page))

  return next
}

/** ¿La URL ya está escrita en su forma canónica? Si no, hay que reemplazarla. */
export function isCanonical(params: URLSearchParams): boolean {
  const canonical = withFilters(params, parseFilters(params))

  return FILTER_KEYS.every((key) => params.get(key) === canonical.get(key))
}
