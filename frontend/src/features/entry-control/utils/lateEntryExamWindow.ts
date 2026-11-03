import type { InProgressExamRow } from '@/features/exams'

const DATE_FORMAT = /^\d{4}-\d{2}-\d{2}$/
const TIME_FORMAT = /^\d{2}:\d{2}(:\d{2})?$/

/** Fin programado de la fila en hora local del campus; null si falta o no tiene el formato esperado. */
export function scheduledEndOf(exam: InProgressExamRow): number | null {
  if (!exam.date || !exam.endsAt || !DATE_FORMAT.test(exam.date) || !TIME_FORMAT.test(exam.endsAt)) return null
  const end = Date.parse(`${exam.date}T${exam.endsAt.slice(0, 5)}:00`)

  return Number.isNaN(end) ? null : end
}

/** Exámenes en los que todavía cabe un ingreso con retraso: el servidor lo vuelve a comprobar. */
export function openForLateEntry(exams: InProgressExamRow[], now: number): InProgressExamRow[] {
  return exams.filter((exam) => {
    const end = scheduledEndOf(exam)
    return end === null || now < end
  })
}
