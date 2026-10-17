import { describe, expect, it } from 'vitest'
import { isCanonical, parseFilters, withFilters } from './filters'

const params = (search: string) => new URLSearchParams(search)

describe('parseFilters', () => {
  it('sin parámetros devuelve los valores por defecto', () => {
    expect(parseFilters(params(''))).toEqual({ q: '', tipo: null, page: 1 })
  })

  it('lee búsqueda, tipo y página', () => {
    expect(parseFilters(params('q=jose&tipo=3&page=2'))).toEqual({ q: 'jose', tipo: 3, page: 2 })
  })

  it('recorta la búsqueda', () => {
    expect(parseFilters(params('q=%20%20jose%20')).q).toBe('jose')
  })

  it.each([['abc'], ['0'], ['-1'], ['1.5'], ['2147483648'], ['99999999999999'], [''], ['1e3']])(
    'un tipo inválido (%s) cae a null',
    (value) => {
      expect(parseFilters(params(`tipo=${value}`)).tipo).toBeNull()
    }
  )

  it.each([['abc'], ['0'], ['-2'], ['100001'], ['2.5'], ['']])('una página inválida (%s) cae a 1', (value) => {
    expect(parseFilters(params(`page=${value}`)).page).toBe(1)
  })

  it('una búsqueda de más de 50 caracteres se descarta', () => {
    expect(parseFilters(params(`q=${'a'.repeat(51)}`)).q).toBe('')
    expect(parseFilters(params(`q=${'a'.repeat(50)}`)).q).toHaveLength(50)
  })
})

describe('withFilters', () => {
  it('no escribe los valores por defecto', () => {
    expect(withFilters(params('q=x&tipo=2&page=3'), { q: '', tipo: null, page: 1 }).toString()).toBe('')
  })

  it('escribe los filtros activos y conserva otros parámetros', () => {
    expect(withFilters(params('otro=1'), { q: 'jose', tipo: 2, page: 3 }).toString()).toBe(
      'otro=1&q=jose&tipo=2&page=3'
    )
  })
})

describe('isCanonical', () => {
  it('acepta una URL limpia y rechaza valores inválidos o redundantes', () => {
    expect(isCanonical(params('q=jose&tipo=2&page=2'))).toBe(true)
    expect(isCanonical(params(''))).toBe(true)
    expect(isCanonical(params('tipo=abc'))).toBe(false)
    expect(isCanonical(params('page=1'))).toBe(false)
    expect(isCanonical(params('page=0'))).toBe(false)
    expect(isCanonical(params('q=%20jose'))).toBe(false)
  })
})
