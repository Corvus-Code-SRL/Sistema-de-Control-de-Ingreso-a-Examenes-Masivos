import { describe, expect, it } from 'vitest'
import {
  isCanonicalStudentFilters,
  parseStudentFilters,
  withStudentFilters,
} from './studentFilters'

const params = (search: string) => new URLSearchParams(search)

describe('parseStudentFilters', () => {
  it('sin parámetros devuelve los valores por defecto', () => {
    expect(parseStudentFilters(params(''))).toEqual({ q: '', page: 1 })
  })

  it('lee búsqueda y página, y recorta la búsqueda', () => {
    expect(parseStudentFilters(params('q=%20nunez%20&page=2'))).toEqual({ q: 'nunez', page: 2 })
  })

  it.each([['abc'], ['0'], ['-2'], ['100001'], ['2.5'], [''], ['1e3']])(
    'una página inválida (%s) cae a 1',
    (value) => {
      expect(parseStudentFilters(params(`page=${value}`)).page).toBe(1)
    }
  )

  it('una búsqueda de más de 50 caracteres se descarta', () => {
    expect(parseStudentFilters(params(`q=${'a'.repeat(51)}`)).q).toBe('')
    expect(parseStudentFilters(params(`q=${'a'.repeat(50)}`)).q).toHaveLength(50)
  })
})

describe('withStudentFilters', () => {
  it('no escribe los valores por defecto', () => {
    expect(withStudentFilters(params('q=x&page=3'), { q: '', page: 1 }).toString()).toBe('')
  })

  it('escribe los filtros activos y conserva otros parámetros', () => {
    expect(withStudentFilters(params('otro=1'), { q: 'maria', page: 3 }).toString()).toBe(
      'otro=1&q=maria&page=3'
    )
  })
})

describe('isCanonicalStudentFilters', () => {
  it('acepta una URL limpia y rechaza valores inválidos o redundantes', () => {
    expect(isCanonicalStudentFilters(params('q=maria&page=2'))).toBe(true)
    expect(isCanonicalStudentFilters(params(''))).toBe(true)
    expect(isCanonicalStudentFilters(params('page=1'))).toBe(false)
    expect(isCanonicalStudentFilters(params('page=abc'))).toBe(false)
    expect(isCanonicalStudentFilters(params('q=%20maria'))).toBe(false)
  })
})

describe('filtro por carrera (HU-021, multicarrera)', () => {
  it('lee la carrera de la URL', () => {
    expect(parseStudentFilters(params('carrera=7&page=2'))).toEqual({ q: '', page: 2, carrera: 7 })
  })

  it.each([['abc'], ['0'], ['-1'], ['2.5'], ['']])('una carrera inválida (%s) se ignora', (value) => {
    expect(parseStudentFilters(params(`carrera=${value}`))).toEqual({ q: '', page: 1 })
  })

  it('escribe la carrera y la quita cuando no hay', () => {
    expect(withStudentFilters(params(''), { q: '', page: 1, carrera: 7 }).toString()).toBe('carrera=7')
    expect(withStudentFilters(params('carrera=7'), { q: '', page: 1 }).toString()).toBe('')
  })

  it('una carrera inválida en la URL no es canónica', () => {
    expect(isCanonicalStudentFilters(params('carrera=abc'))).toBe(false)
    expect(isCanonicalStudentFilters(params('carrera=7'))).toBe(true)
  })
})
