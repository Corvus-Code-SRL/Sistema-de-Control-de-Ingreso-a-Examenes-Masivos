import { describe, expect, it } from 'vitest'
import { formatDate, formatTime } from './eligibleListUtils'

describe('eligibleListUtils', () => {
  it('formatTime muestra la hora del servidor como HH:MM sin convertir la zona horaria', () => {
    // La hora ya viene en hora local de La Paz: el resultado no puede depender de TZ del entorno.
    expect(formatTime('10:00')).toBe('10:00')
    expect(formatTime('07:30:00')).toBe('07:30')
    expect(formatTime('23:59:59')).toBe('23:59')
    expect(formatTime('sin hora')).toBe('sin hora')
  })

  it('formatDate no se desplaza un día en ninguna zona horaria', () => {
    expect(formatDate('2026-10-20')).toBe('20/10/2026')
  })
})
