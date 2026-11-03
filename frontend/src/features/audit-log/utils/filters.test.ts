import { describe, expect, it } from 'vitest'
import {
  NO_FILTERS,
  RANGE_ERROR,
  detailIdFrom,
  hasActiveFilters,
  isCanonical,
  parseFilters,
  rangeError,
  validDate,
  withFilters,
} from './filters'

const params = (query: string) => new URLSearchParams(query)

describe('validDate', () => {
  it.each(['2026-10-19', '2024-02-29'])('acepta %s', (value) => {
    expect(validDate(value)).toBe(value)
  })

  it.each([null, '', '2026-02-31', '2026-13-01', '19/10/2026', '2026-1-1', '2026-10-19T00:00'])(
    'descarta %s',
    (value) => {
      expect(validDate(value)).toBeNull()
    }
  )
})

describe('parseFilters', () => {
  it('lee todos los filtros', () => {
    expect(
      parseFilters(params('q=marcelo&accion=MODIFICAR&modulo=grupos&desde=2026-10-01&hasta=2026-10-31&page=2'))
    ).toEqual({
      q: 'marcelo',
      accion: 'MODIFICAR',
      modulo: 'grupos',
      desde: '2026-10-01',
      hasta: '2026-10-31',
      page: 2,
    })
  })

  it('sin parámetros devuelve los valores por defecto', () => {
    expect(parseFilters(params(''))).toEqual(NO_FILTERS)
  })

  it('descarta valores inválidos sin fallar', () => {
    expect(
      parseFilters(params(`q=${'a'.repeat(101)}&accion=modificar&modulo=Grupos!&desde=hoy&hasta=2026-02-31&page=0`))
    ).toEqual(NO_FILTERS)
  })

  it.each(['abc', '-1', '1.5', '100001', '9999999'])('la página «%s» vuelve a la primera', (page) => {
    expect(parseFilters(params(`page=${page}`)).page).toBe(1)
  })

  it('recorta los espacios de la búsqueda', () => {
    expect(parseFilters(params('q=%20%20marcelo%20')).q).toBe('marcelo')
  })
})

describe('withFilters', () => {
  it('no escribe los valores por defecto', () => {
    expect(withFilters(params('q=x&page=3'), NO_FILTERS).toString()).toBe('')
  })

  it('conserva los parámetros que no son filtros', () => {
    const next = withFilters(params('detalle=130&q=viejo'), { ...NO_FILTERS, q: 'nuevo', page: 2 })

    expect(next.get('detalle')).toBe('130')
    expect(next.get('q')).toBe('nuevo')
    expect(next.get('page')).toBe('2')
  })
})

describe('isCanonical', () => {
  it('una URL ya canónica no se reescribe', () => {
    expect(isCanonical(params('q=marcelo&page=2'))).toBe(true)
    expect(isCanonical(params('detalle=130'))).toBe(true)
    expect(isCanonical(params(''))).toBe(true)
  })

  it.each(['page=1', 'page=abc', 'desde=2026-02-31', 'accion=minuscula', 'q=%20x'])(
    '«%s» se debe reemplazar',
    (query) => {
      expect(isCanonical(params(query))).toBe(false)
    }
  )
})

describe('hasActiveFilters', () => {
  it('la página no cuenta como filtro', () => {
    expect(hasActiveFilters({ ...NO_FILTERS, page: 4 })).toBe(false)
  })

  it.each([{ q: 'a' }, { accion: 'CREAR' }, { modulo: 'grupos' }, { desde: '2026-10-01' }, { hasta: '2026-10-01' }])(
    '%o cuenta como filtro',
    (change) => {
      expect(hasActiveFilters({ ...NO_FILTERS, ...change })).toBe(true)
    }
  )
})

describe('rangeError', () => {
  it('«Desde» posterior a «Hasta» es un error', () => {
    expect(rangeError({ desde: '2026-10-20', hasta: '2026-10-19' })).toBe(RANGE_ERROR)
  })

  it.each([
    { desde: '2026-10-19', hasta: '2026-10-20' },
    { desde: '2026-10-19', hasta: '2026-10-19' },
    { desde: '2026-10-19', hasta: null },
    { desde: null, hasta: '2026-10-19' },
    { desde: null, hasta: null },
  ])('%o es válido', (range) => {
    expect(rangeError(range)).toBeNull()
  })
})

describe('detailIdFrom', () => {
  it('sin `detalle` no hay panel', () => {
    expect(detailIdFrom(params('q=x'))).toEqual({ present: false, id: null })
  })

  it('un id válido abre el panel', () => {
    expect(detailIdFrom(params('detalle=130'))).toEqual({ present: true, id: 130 })
  })

  it.each(['abc', '0', '-3', '1.5', '1234567890', '007'])('«%s» abre el panel con error', (value) => {
    expect(detailIdFrom(params(`detalle=${value}`))).toEqual({ present: true, id: null })
  })
})
