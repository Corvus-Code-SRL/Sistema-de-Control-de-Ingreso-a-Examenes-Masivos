import { describe, expect, it } from 'vitest'
import { examOptionLabel, formatExamDate, formatRooms, formatTimeRange } from './collaboratorFormat'
import type { InvitableExam } from '../types/collaborator.types'

describe('collaboratorFormat', () => {
  it('formatExamDate reordena el texto sin correr el día por la zona horaria', () => {
    expect(formatExamDate('2026-12-10')).toBe('10/12/2026')
    expect(formatExamDate(null)).toBe('Sin fecha')
  })

  it('formatTimeRange recorta los segundos y tolera un fin ausente', () => {
    expect(formatTimeRange('08:00:00', '09:30:00')).toBe('08:00–09:30')
    expect(formatTimeRange('08:00', null)).toBe('08:00')
    expect(formatTimeRange(null, null)).toBe('')
  })

  it('formatRooms distingue uno, varios y ninguno', () => {
    expect(formatRooms([])).toBe('Sin ambiente')
    expect(formatRooms([{ nro_aula: '691A' }])).toBe('Aula 691A')
    expect(formatRooms([{ nro_aula: '691A' }, { nro_aula: '692B' }])).toBe('Aulas 691A, 692B')
  })

  it('examOptionLabel omite lo que falta sin dejar separadores sueltos', () => {
    const exam: InvitableExam = {
      id_examen: 1,
      nombre_examen: 'Parcial',
      fecha: null,
      hora_inicio: null,
      hora_fin: null,
      materia: null,
      carrera: null,
      ambientes: [],
    }

    expect(examOptionLabel(exam)).toBe('Parcial · Sin materia · Sin fecha · Sin ambiente')
  })
})
