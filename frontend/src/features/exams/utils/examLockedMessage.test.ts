import { describe, expect, it } from 'vitest'
import type { ExamStatus } from '../types/exams.types'
import { examLockedMessage } from './examLockedMessage'

describe('examLockedMessage', () => {
  it('cada estado tiene su propio texto, que nombra el estado real', () => {
    expect(examLockedMessage('CANCELADO')).toMatch(/cancelado/i)
    expect(examLockedMessage('FINALIZADO')).toMatch(/finaliz/i)
    expect(examLockedMessage('EN_CURSO')).toMatch(/en curso/i)
    expect(examLockedMessage('PROGRAMADO', true)).toMatch(/control de ingreso/i)
  })

  it('un examen cancelado o finalizado nunca dice que inició el control de ingreso', () => {
    expect(examLockedMessage('CANCELADO')).not.toMatch(/control de ingreso/i)
    expect(examLockedMessage('FINALIZADO')).not.toMatch(/control de ingreso/i)
  })

  it('los cuatro estados y el PROGRAMADO con el ingreso abierto producen cinco textos distintos', () => {
    const states: ExamStatus[] = ['PROGRAMADO', 'EN_CURSO', 'FINALIZADO', 'CANCELADO']
    const messages = [...states.map((state) => examLockedMessage(state)), examLockedMessage('PROGRAMADO', true)]

    expect(new Set(messages).size).toBe(5)
  })

  it('un examen PROGRAMADO sin abrir el control sigue editable', () => {
    expect(examLockedMessage('PROGRAMADO')).toMatch(/puede modificar/i)
    expect(examLockedMessage('PROGRAMADO')).not.toMatch(/control de ingreso/i)
  })
})
