import { describe, expect, it } from 'vitest'
import { formatDate, formatDayMonthTime, formatFileSize, formatPlainDate, formatTime, initialsOf } from './format'

describe('formatDate y formatTime (hora de La Paz)', () => {
  it('da la fecha en dd/mm/aaaa', () => {
    expect(formatDate('2026-10-20T14:21:00+00:00')).toBe('20/10/2026')
  })

  it('pasa la hora UTC a la de La Paz: 14:03Z es 10:03', () => {
    expect(formatTime('2026-10-20T14:03:00+00:00')).toBe('10:03')
  })

  it('una medianoche de La Paz sigue siendo del día local, no del UTC', () => {
    // 02:30Z del 21 es 22:30 del 20 en La Paz.
    expect(formatDate('2026-10-21T02:30:00+00:00')).toBe('20/10/2026')
    expect(formatTime('2026-10-21T02:30:00+00:00')).toBe('22:30')
  })

  it('formatDayMonthTime junta día, mes y hora', () => {
    expect(formatDayMonthTime('2026-10-20T14:05:00+00:00')).toBe('20/10 10:05')
  })

  it('la hora usa 24 horas', () => {
    expect(formatTime('2026-10-20T21:05:00+00:00')).toBe('17:05')
  })
})

describe('formatPlainDate', () => {
  it('reordena YYYY-MM-DD sin pasar por la zona horaria', () => {
    expect(formatPlainDate('2026-10-20')).toBe('20/10/2026')
  })

  it('devuelve tal cual lo que no tiene la forma esperada', () => {
    expect(formatPlainDate('sin fecha')).toBe('sin fecha')
  })
})

describe('formatFileSize', () => {
  it.each([
    [512, '512 B'],
    [2048, '2 KB'],
    [1_258_291, '1,2 MB'],
  ])('%i bytes → %s', (bytes, expected) => {
    expect(formatFileSize(bytes)).toBe(expected)
  })
})

describe('initialsOf', () => {
  it.each([
    ['José Luis Pérez Núñez', 'JP'],
    ['Lucía Salazar Terán', 'LS'],
    ['Ana Pérez', 'AP'],
    ['Ana', 'A'],
    ['', '?'],
  ])('%s → %s', (name, initials) => {
    expect(initialsOf(name)).toBe(initials)
  })
})
