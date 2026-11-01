import { describe, expect, it } from 'vitest'
import { isCanonicalAccountUrl, parseAccountFilters, withAccountFilters } from './accountFilters'

const params = (query: string) => new URLSearchParams(query)

describe('parseAccountFilters', () => {
  it('lee búsqueda, rol y página', () => {
    expect(parseAccountFilters(params('q=%20ana%20&rol=2&page=3'))).toEqual({ q: 'ana', rol: '2', page: 3 })
    expect(parseAccountFilters(params('rol=sin-rol'))).toEqual({ q: '', rol: 'sin-rol', page: 1 })
  })

  it.each(['rol=todos', 'rol=1;drop', 'rol=0', 'rol=1234567890', 'page=0', 'page=abc', 'page=100001'])(
    'descarta el valor inválido %s',
    (query) => {
      expect(parseAccountFilters(params(query))).toEqual({ q: '', rol: null, page: 1 })
    }
  )

  it('descarta una búsqueda de más de 50 caracteres', () => {
    expect(parseAccountFilters(params(`q=${'a'.repeat(51)}`)).q).toBe('')
  })
})

describe('withAccountFilters', () => {
  it('no escribe los valores por defecto y conserva el resto de la URL', () => {
    const next = withAccountFilters(params('otro=1&q=x&page=4'), { q: '', rol: null, page: 1 })

    expect(next.toString()).toBe('otro=1')
  })

  it('escribe lo que se filtra', () => {
    expect(withAccountFilters(params(''), { q: 'ana', rol: 'sin-rol', page: 2 }).toString()).toBe(
      'q=ana&rol=sin-rol&page=2'
    )
  })
})

describe('isCanonicalAccountUrl', () => {
  it('distingue una URL limpia de una con valores inválidos', () => {
    expect(isCanonicalAccountUrl(params('q=ana&rol=2'))).toBe(true)
    expect(isCanonicalAccountUrl(params('rol=todos'))).toBe(false)
    expect(isCanonicalAccountUrl(params('page=1'))).toBe(false)
  })
})
