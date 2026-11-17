import { describe, expect, it } from 'vitest'
import { firstPartial } from '@/test/invitationFixtures'
import {
  STATE_LABEL,
  canRespond,
  capacityCheck,
  firstName,
  formatExamLong,
  formatExamShort,
  formatMoment,
  initials,
  joinGroups,
} from './invitationFormat'

describe('invitationFormat', () => {
  it('formatea la fecha y hora locales del examen sin convertir zona', () => {
    expect(formatExamShort('2026-10-20', '10:00:00')).toBe('mar 20/10 · 10:00')
    expect(formatExamLong('2026-11-13', '08:15:00', '10:00:00')).toBe('vie 13/11/2026 · 08:15 a 10:00')
    expect(formatExamLong('2026-11-13', '08:15', null)).toBe('vie 13/11/2026 · 08:15')
  })

  it('un instante guardado en UTC se muestra en hora de La Paz', () => {
    expect(formatMoment('2026-10-20T12:12:00+00:00')).toBe('20/10 08:12')
    // 02:30 UTC del 21 es todavía el 20 en La Paz.
    expect(formatMoment('2026-10-21T02:30:00+00:00')).toBe('20/10 22:30')
  })

  it('«Sin efecto» se muestra como «Vencida» y solo una pendiente se puede responder', () => {
    expect(STATE_LABEL.SIN_EFECTO).toBe('Vencida')
    expect(canRespond('PENDIENTE')).toBe(true)
    expect(canRespond('ACEPTADA')).toBe(false)
    expect(canRespond('RECHAZADA')).toBe(false)
    expect(canRespond('SIN_EFECTO')).toBe(false)
  })

  it('nombres e iniciales', () => {
    expect(initials('Marcelo Quiroga Andrade')).toBe('MQ')
    expect(initials('Rosario')).toBe('RO')
    expect(firstName('Marcelo Quiroga Andrade')).toBe('Marcelo')
  })

  it('une los grupos como se leen', () => {
    expect(joinGroups([{ num_grupo: '3' }])).toBe('Grupo 3')
    expect(joinGroups([{ num_grupo: '3' }, { num_grupo: '6' }])).toBe('Grupo 3 y Grupo 6')
    expect(joinGroups([{ num_grupo: '1' }, { num_grupo: '3' }, { num_grupo: '6' }])).toBe('Grupo 1, Grupo 3 y Grupo 6')
  })

  it('la capacidad compara todos los estudiantes con la suma de los ambientes (CA-9)', () => {
    expect(capacityCheck(firstPartial, [])).toEqual({ current: 55, after: 55, capacity: 60, exceeds: false })
    expect(capacityCheck(firstPartial, [3, 6])).toEqual({ current: 55, after: 115, capacity: 60, exceeds: true })
    expect(capacityCheck(firstPartial, [6]).exceeds).toBe(true)
  })
})
