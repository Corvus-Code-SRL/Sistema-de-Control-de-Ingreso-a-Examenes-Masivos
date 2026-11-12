import type { GroupStudentFilters } from '../types/group.types'

const MAX_PAGE = 100000
export const MAX_SEARCH_LENGTH = 50

const FILTER_KEYS = ['q', 'page', 'carrera'] as const

const MAX_ID = 2147483647

/** Página escrita solo con dígitos y dentro del rango que acepta el servidor; si no, null. */
function boundedPage(value: string | null): number | null {
  if (value === null || !/^\d{1,6}$/.test(value)) return null

  const number = Number(value)

  return number >= 1 && number <= MAX_PAGE ? number : null
}

/** Identificador de carrera escrito solo con dígitos y dentro del rango de la base; si no, null. */
function boundedId(value: string | null): number | null {
  if (value === null || !/^\d{1,10}$/.test(value)) return null

  const number = Number(value)

  return number >= 1 && number <= MAX_ID ? number : null
}

/** Lee los filtros de la URL; lo que no sea válido vuelve al valor por defecto, sin avisar. */
export function parseStudentFilters(params: URLSearchParams): GroupStudentFilters {
  const q = (params.get('q') ?? '').trim()
  const carrera = boundedId(params.get('carrera'))

  return {
    q: q.length <= MAX_SEARCH_LENGTH ? q : '',
    page: boundedPage(params.get('page')) ?? 1,
    // Sin carrera la clave no aparece: «todas» es el valor por defecto.
    ...(carrera !== null ? { carrera } : {}),
  }
}

/** Aplica los filtros a una URL conservando el resto de sus parámetros; los valores por defecto no se escriben. */
export function withStudentFilters(
  current: URLSearchParams,
  filters: GroupStudentFilters
): URLSearchParams {
  const next = new URLSearchParams(current)

  FILTER_KEYS.forEach((key) => next.delete(key))

  if (filters.q !== '') next.set('q', filters.q)
  if (filters.page > 1) next.set('page', String(filters.page))
  if (filters.carrera !== undefined) next.set('carrera', String(filters.carrera))

  return next
}

/** ¿La URL ya está escrita en su forma canónica? Si no, hay que reemplazarla. */
export function isCanonicalStudentFilters(params: URLSearchParams): boolean {
  const canonical = withStudentFilters(params, parseStudentFilters(params))

  return FILTER_KEYS.every((key) => params.get(key) === canonical.get(key))
}
