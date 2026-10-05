import { describe, expect, it } from 'vitest'
import type { ExamStatus } from '../types/exams.types'
import { examLockedMessage } from './examLockedMessage'

describe('examLockedMessage', () => {
  it('cada estado tiene su propio texto, que nombra el estado real', () => {
    expect(examLockedMessage('CANCELADO')).toMatch(/cancelado/i)
    expect(examLockedMessage('FINALIZADO')).toMatch(/finaliz/i)
    expect(examLockedMessage('EN_CURSO')).toMatch(/en curso/i)
    expect(examLockedMessage('EN_INGRESO')).toMatch(/control de ingreso/i)
  })

  it('un examen cancelado o finalizado nunca dice que inició el control de ingreso', () => {
    expect(examLockedMessage('CANCELADO')).not.toMatch(/control de ingreso/i)
    expect(examLockedMessage('FINALIZADO')).not.toMatch(/control de ingreso/i)
  })

  it('los cinco estados producen cinco textos distintos', () => {
    const states: ExamStatus[] = ['PROGRAMADO', 'EN_INGRESO', 'EN_CURSO', 'FINALIZADO', 'CANCELADO']

    expect(new Set(states.map(examLockedMessage)).size).toBe(5)
  })
})
