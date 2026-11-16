import { describe, expect, it } from 'vitest'
import { makeLateEntryVerification } from '@/test/lateEntryFixtures'
import {
  MANUAL_REJECTION_VERDICT, toManualRejection, toRejectedAttempt, withManualRejection, withReported,
} from './rejectedAttempt'

const student = { id_estudiante: 501, cod_sis: '202301903', nombre_completo: 'Lucía Fernández Rojas' }

describe('rejectedAttempt', () => {
  it('un resultado negativo con intento y estudiante queda listo para reportar', () => {
    const verdict = makeLateEntryVerification({
      autorizado: false, veredicto: 'NO_PERTENECE', motivo: 'No pertenece a los grupos de este examen',
      id_intento: 40, registrado_en: '2026-10-31T14:00:00Z', reportado: true,
    })

    expect(toRejectedAttempt(verdict, 6)).toEqual({
      attemptId: 40, student: verdict.estudiante, roomId: 6, registeredAt: '2026-10-31T14:00:00Z',
      reason: 'No pertenece a los grupos de este examen', reported: true,
    })
  })

  it('sin intento, sin estudiante o autorizado no hay nada que reportar', () => {
    expect(toRejectedAttempt(makeLateEntryVerification(), 6)).toBeNull()
    expect(toRejectedAttempt(makeLateEntryVerification({ autorizado: false, id_intento: null }), 6)).toBeNull()
    expect(toRejectedAttempt(makeLateEntryVerification({ autorizado: false, id_intento: 40, estudiante: null }), 6))
      .toBeNull()
  })

  it('el rechazo manual muestra «Ingreso rechazado» con el motivo legible', () => {
    const attempt = toManualRejection(
      { id_intento: 41, registrado: true, motivo: 'DECISION_CONTROLADOR', registrado_en: '2026-10-31T14:05:00Z' },
      student,
      5,
    )
    const result = withManualRejection(makeLateEntryVerification(), attempt)

    expect(attempt).toEqual({
      attemptId: 41, student, roomId: 5, registeredAt: '2026-10-31T14:05:00Z',
      reason: 'Rechazo por decisión del controlador', reported: false,
    })
    expect(result).toMatchObject({
      autorizado: false, veredicto: MANUAL_REJECTION_VERDICT, motivo: 'Rechazo por decisión del controlador',
      id_intento: 41, reportado: false,
    })
    expect(withReported(result, 41)?.reportado).toBe(true)
    expect(withReported(result, 99)).toBe(result)
    expect(withReported(null, 41)).toBeNull()
  })
})
