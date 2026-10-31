import type { ExamStatus } from '../types/exams.types'

/**
 * Por qué un examen que ya no está PROGRAMADO se muestra de solo lectura. El texto debe decir el
 * estado real: uno cancelado o finalizado nunca "inició el control de ingreso".
 */
export function examLockedMessage(status: ExamStatus): string {
  switch (status) {
    case 'CANCELADO':
      return 'Este examen fue cancelado: ya no admite cambios y no se tomará.'
    case 'FINALIZADO':
      return 'Este examen ya finalizó: su información general y sus grupos quedaron fijos.'
    case 'EN_CURSO':
      return 'Este examen está en curso: su información general y sus grupos quedaron fijos.'
    case 'EN_INGRESO':
      return 'El control de ingreso de este examen ya se inició: la información general y sus grupos quedaron fijos.'
    case 'PROGRAMADO':
      return 'Este examen está programado: puede modificar su información general y sus grupos.'
  }
}
