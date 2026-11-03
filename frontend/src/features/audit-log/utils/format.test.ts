import { describe, expect, it } from 'vitest'
import { EMPTY_VALUE, formatDateTime, formatValue, initialsOf } from './format'

describe('formatDateTime', () => {
  it('muestra la hora de La Paz (UTC-4) sin importar la zona del navegador', () => {
    expect(formatDateTime('2026-10-20T14:21:00+00:00')).toBe('20/10/2026 10:21')
  })

  it('23:30 del 19/10 en La Paz sigue siendo 19/10 aunque en UTC ya sea 20/10', () => {
    expect(formatDateTime('2026-10-20T03:30:00+00:00')).toBe('19/10/2026 23:30')
  })

  it('medianoche de La Paz es 00:00 y no 24:00', () => {
    expect(formatDateTime('2026-10-20T04:00:00+00:00')).toBe('20/10/2026 00:00')
  })

  it('acepta un instante con desplazamiento explícito', () => {
    expect(formatDateTime('2026-10-20T10:21:00-04:00')).toBe('20/10/2026 10:21')
  })
})

describe('formatValue', () => {
  it.each([undefined, null, ''])('%s sale como «(sin valor)»', (value) => {
    expect(formatValue(value)).toBe(EMPTY_VALUE)
  })

  it('los booleanos salen como «Sí» y «No»', () => {
    expect(formatValue(true)).toBe('Sí')
    expect(formatValue(false)).toBe('No')
  })

  it('el cero no se confunde con vacío', () => {
    expect(formatValue(0)).toBe('0')
  })

  it('textos y números salen tal cual', () => {
    expect(formatValue('PROGRAMADO')).toBe('PROGRAMADO')
    expect(formatValue(5)).toBe('5')
  })

  it('listas y objetos salen como JSON en una línea', () => {
    expect(formatValue([1, 2])).toBe('[1,2]')
    expect(formatValue({ a: 1 })).toBe('{"a":1}')
  })
})

describe('initialsOf', () => {
  it('toma el primer nombre y el penúltimo término', () => {
    expect(initialsOf('Marcelo Quiroga Andrade')).toBe('MQ')
    expect(initialsOf('Daniela Ferrufino')).toBe('DF')
  })

  it('un nombre vacío da un signo de interrogación', () => {
    expect(initialsOf('   ')).toBe('?')
  })
})
