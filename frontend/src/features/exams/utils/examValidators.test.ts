import { describe, expect, it } from 'vitest'
import type { CreateExamFormData, Group } from '../types/exams.types'
import { localToday, validateExamForm, validateGroupsStep } from './examValidators'

// 10:00 hora local del navegador.
const now = new Date(2026, 8, 21, 10, 0)

function form(overrides: Partial<CreateExamFormData> = {}): CreateExamFormData {
  return {
    nombre_examen: 'Primer parcial',
    materia: { id_carrera: 1, id_materia: 2 },
    categoria: 'REGULAR',
    fecha: '2026-09-22',
    hora_inicio: '08:00',
    duracion: 90,
    ambientes: [1],
    grupos: [],
    normas: '',
    ...overrides,
  }
}

describe('validateExamForm', () => {
  it('acepta un examen completo en el futuro', () => {
    expect(validateExamForm(form(), now).isValid).toBe(true)
  })

  it('exige materia como par materia-carrera', () => {
    expect(validateExamForm(form({ materia: null }), now).errors.materia).toBeDefined()
  })

  it('rechaza una hora ya pasada del mismo día', () => {
    const result = validateExamForm(form({ fecha: '2026-09-21', hora_inicio: '09:59' }), now)

    expect(result.errors.fecha).toBe(
      'La fecha y hora del examen no pueden ser anteriores al momento actual.'
    )
    expect(validateExamForm(form({ fecha: '2026-09-21', hora_inicio: '10:30' }), now).isValid).toBe(true)
  })

  it.each([0, -5, 1.5, 1440, Number.NaN])('rechaza la duración %s', (duracion) => {
    expect(validateExamForm(form({ duracion }), now).errors.duracion).toBeDefined()
  })

  it('acepta duraciones cortas y exámenes que cruzan la medianoche', () => {
    expect(validateExamForm(form({ duracion: 1 }), now).isValid).toBe(true)
    expect(validateExamForm(form({ hora_inicio: '23:00', duracion: 120 }), now).isValid).toBe(true)
  })
})

describe('localToday', () => {
  it('usa la fecha local, no la UTC', () => {
    expect(localToday(new Date(2026, 8, 21, 23, 30))).toBe('2026-09-21')
  })
})

const availableGroup: Group = {
  id_grupo: 10,
  id_carrera: 1,
  id_materia: 2,
  num_grupo: '1',
  gestion: '2026',
  estado: 'ACTIVO',
  cantidad_estudiantes: 30,
  tiene_nomina: true,
}

describe('validateGroupsStep', () => {
  it('acepta un grupo disponible con nómina', () => {
    const result = validateGroupsStep(form({ grupos: [availableGroup.id_grupo] }), [
      availableGroup,
    ])

    expect(result.isValid).toBe(true)
  })

  it('rechaza grupos vacíos, ajenos al par o sin nómina', () => {
    expect(validateGroupsStep(form({ grupos: [] }), [availableGroup]).isValid).toBe(false)
    expect(validateGroupsStep(form({ grupos: [999] }), [availableGroup]).isValid).toBe(false)
    expect(
      validateGroupsStep(
        form({ grupos: [availableGroup.id_grupo] }),
        [{ ...availableGroup, tiene_nomina: false, cantidad_estudiantes: 0 }]
      ).isValid
    ).toBe(false)
  })
})
