import { WITHOUT_ROLE_FILTER, type AccountFilters } from '../types/users.types'

const MAX_PAGE = 100000
export const MAX_SEARCH_LENGTH = 50

const FILTER_KEYS = ['q', 'rol', 'page'] as const

/** Entero positivo escrito solo con dígitos y dentro del rango que acepta el servidor; si no, null. */
function boundedInteger(value: string | null, max: number): number | null {
  if (value === null || !/^\d{1,9}$/.test(value)) return null

  const number = Number(value)

  return number >= 1 && number <= max ? number : null
}

/** Un rol del filtro es un id del catálogo o `sin-rol`; cualquier otro valor se descarta. */
function parseRole(value: string | null): string | null {
  if (value === WITHOUT_ROLE_FILTER) return value

  const id = boundedInteger(value, 999999999)

  return id === null ? null : String(id)
}

/** Lee los filtros de la URL; lo que no sea válido vuelve al valor por defecto, sin avisar. */
export function parseAccountFilters(params: URLSearchParams): AccountFilters {
  const q = (params.get('q') ?? '').trim()

  return {
    q: q.length <= MAX_SEARCH_LENGTH ? q : '',
    rol: parseRole(params.get('rol')),
    page: boundedInteger(params.get('page'), MAX_PAGE) ?? 1,
  }
}

/** Aplica los filtros a una URL conservando el resto de sus parámetros; los valores por defecto no se escriben. */
export function withAccountFilters(current: URLSearchParams, filters: AccountFilters): URLSearchParams {
  const next = new URLSearchParams(current)

  FILTER_KEYS.forEach((key) => next.delete(key))

  if (filters.q !== '') next.set('q', filters.q)
  if (filters.rol !== null) next.set('rol', filters.rol)
  if (filters.page > 1) next.set('page', String(filters.page))

  return next
}

/** ¿La URL ya está escrita en su forma canónica? Si no, hay que reemplazarla. */
export function isCanonicalAccountUrl(params: URLSearchParams): boolean {
  const canonical = withAccountFilters(params, parseAccountFilters(params))

  return FILTER_KEYS.every((key) => params.get(key) === canonical.get(key))
}
