import type { SituationExam } from '../types/exam-situation.types'

/** Textos del acceso independiente de situaciones excepcionales (HU-057). */
export const EXAM_SITUATION_MESSAGES = {
  emptyTitle: 'No tiene exámenes con ingreso abierto ni en curso',
  emptyDescription:
    'Las situaciones excepcionales se registran desde que se abre el control de ingreso hasta que termina el examen.',
} as const

export const SITUATION_EXAM_STATE_LABELS: Record<SituationExam['estado'], string> = {
  EN_INGRESO: 'En ingreso',
  EN_CURSO: 'En curso',
}
