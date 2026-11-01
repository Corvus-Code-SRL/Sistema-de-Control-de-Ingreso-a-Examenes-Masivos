import { describe, expect, it } from 'vitest'
import type { InProgressExamRow } from '@/features/exams'
import { openForLateEntry, scheduledEndOf } from './lateEntryExamWindow'

function row(id: number, date: string | null, endsAt: string | null): InProgressExamRow {
  return { id, title: `Examen ${id}`, subject: null, date, startsAt: '10:00', endsAt, rooms: '691A' }
}

const now = new Date('2026-10-31T11:00:00').getTime()

describe('lateEntryExamWindow', () => {
  it('el fin programado es la fecha y la hora de fin locales, con o sin segundos', () => {
    expect(scheduledEndOf(row(1, '2026-10-31', '12:00'))).toBe(new Date('2026-10-31T12:00:00').getTime())
    expect(scheduledEndOf(row(1, '2026-10-31', '12:00:00'))).toBe(new Date('2026-10-31T12:00:00').getTime())
  })

  it('deja los exámenes abiertos y quita los que ya llegaron a su fin', () => {
    const exams = [row(1, '2026-10-31', '12:00'), row(2, '2026-10-31', '11:00'), row(3, '2026-10-31', '10:30')]

    expect(openForLateEntry(exams, now).map((exam) => exam.id)).toEqual([1])
  })

  it('sin fecha u hora deja el examen: lo decide el servidor', () => {
    const exams = [row(1, null, '12:00'), row(2, '2026-10-31', null), row(3, null, null)]

    expect(exams.map(scheduledEndOf)).toEqual([null, null, null])
    expect(openForLateEntry(exams, now).map((exam) => exam.id)).toEqual([1, 2, 3])
  })

  it('con datos malformados no inventa una fecha: deja el examen y lo decide el servidor', () => {
    const exams = [
      row(1, 'fecha', 'hora'),
      row(2, '31/10/2026', '12:00'),
      row(3, '2026-10-31', '12h00'),
      row(4, '2026-10-31', '9:00'),
    ]

    expect(exams.map(scheduledEndOf)).toEqual([null, null, null, null])
    expect(openForLateEntry(exams, now).map((exam) => exam.id)).toEqual([1, 2, 3, 4])
  })
})
